<?php

declare(strict_types=1);

namespace Cataloga\Mutation;

use Cataloga\Audit\AuditLogger;
use Cataloga\Registry\EntityRepository;
use Cataloga\Registry\RegistryFileRepository;
use Cataloga\Registry\RelationRepository;
use Cataloga\Registry\PathGuard;
use Cataloga\Registry\RecordSerializer;
use Cataloga\Registry\ResourceDependencyProjector;
use Cataloga\Validation\RegistryValidator;

final class ChangeService
{
    private const ALLOWED_OPERATIONS = ['upsert_entity', 'delete_entity', 'upsert_relation', 'delete_relation', 'set_dependency_slot', 'upsert_settings'];
    private const MUTABLE_STATUSES = ['draft', 'validated', 'open'];
    private const SAVED_STATUSES = ['saved', 'applied', 'committed'];
    private const DISCARDED_STATUSES = ['discarded', 'aborted'];

    public function __construct(
        private readonly EntityRepository $entityRepository,
        private readonly RelationRepository $relationRepository,
        private readonly RecordSerializer $recordSerializer,
        private readonly PathGuard $pathGuard,
        private readonly RegistryFileRepository $registryFileRepository,
        private readonly ResourceDependencyProjector $dependencyProjector,
        private readonly ChangeSessionRepository $changeRepository,
        private readonly RegistryValidator $validator,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function createChange(string $actor = 'unknown', string $actorType = 'unknown'): array
    {
        return $this->changeRepository->create($actor, $this->normalizeActorType($actorType));
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getChange(string $id): ?array
    {
        $session = $this->changeRepository->get($id);
        if ($session === null) {
            return null;
        }

        return $this->normalizeLegacyStatus($session);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listRecentChanges(int $limit = 20): array
    {
        $sessions = $this->changeRepository->listRecent($limit);

        return array_map(fn (array $session): array => $this->normalizeLegacyStatus($session), $sessions);
    }

    /**
     * @param array<int,array<string,mixed>>|array<string,mixed> $operations
     * @return array<string,mixed>
     */
    public function addOperations(string $changeId, array $operations): array
    {
        $session = $this->requireChange($changeId);
        if (!$this->isMutableStatus((string) ($session['status'] ?? 'draft'))) {
            throw new \RuntimeException('Cannot add operations to closed change session.');
        }

        if (isset($operations['type'])) {
            $operations = [$operations];
        }

        foreach ($operations as $operation) {
            if (!is_array($operation)) {
                throw new \RuntimeException('Operation must be an object.');
            }

            $type = (string) ($operation['type'] ?? '');
            if (!in_array($type, self::ALLOWED_OPERATIONS, true)) {
                throw new \RuntimeException('Operation type is not allowed: ' . $type);
            }

            $operation['addedAt'] = gmdate(DATE_ATOM);
            $session['operations'][] = $operation;
        }

        $session['status'] = 'draft';
        $this->changeRepository->save($session);

        return $this->normalizeLegacyStatus($session);
    }

    private function isMutableStatus(string $status): bool
    {
        return in_array($status, self::MUTABLE_STATUSES, true);
    }

    private function isSavedStatus(string $status): bool
    {
        return in_array($status, self::SAVED_STATUSES, true);
    }

    private function isDiscardedStatus(string $status): bool
    {
        return in_array($status, self::DISCARDED_STATUSES, true);
    }

    /**
     * @param array<string,mixed> $session
     * @return array<string,mixed>
     */
    private function normalizeLegacyStatus(array $session): array
    {
        $status = (string) ($session['status'] ?? 'draft');
        if ($status === 'open') {
            $session['status'] = 'draft';
        } elseif ($status === 'applied' || $status === 'committed') {
            $session['status'] = 'saved';
        } elseif ($status === 'aborted') {
            $session['status'] = 'discarded';
        }

        return $session;
    }

    /**
     * @return array<string,mixed>
     */
    public function validateChange(string $changeId): array
    {
        $session = $this->requireChange($changeId);
        $status = (string) ($session['status'] ?? 'draft');
        if ($this->isSavedStatus($status) || $this->isDiscardedStatus($status) || $status === 'failed') {
            throw new \RuntimeException('Cannot validate closed change session.');
        }

        $projection = $this->projectedState($session);
        $registryScan = $this->entityRepository->scanRegistryRecords();
        $validation = $this->validator->validateProjectedState(
            $projection['projectedById'],
            $projection['finalIdPaths'],
            $projection['projectedRelationsById'],
            $projection['finalRelationPaths'],
            $registryScan,
            $projection['errors']
        );

        $session['validation'] = $validation;
        $session['status'] = $validation['valid'] ? 'validated' : 'draft';
        $this->changeRepository->save($session);

        return $session;
    }

    /**
     * @return array<string,mixed>
     */
    public function validateCurrentRegistry(): array
    {
        $session = [
            'operations' => [],
        ];

        $projection = $this->projectedState($session);
        $registryScan = $this->entityRepository->scanRegistryRecords();

        return $this->validator->validateProjectedState(
            $projection['projectedById'],
            $projection['finalIdPaths'],
            $projection['projectedRelationsById'],
            $projection['finalRelationPaths'],
            $registryScan,
            $projection['errors']
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function diffChange(string $changeId): array
    {
        $session = $this->requireChange($changeId);
        $projection = $this->projectedState($session);

        $diff = $this->buildDiff($projection);

        return [
            'changeId' => $changeId,
            'items' => $diff,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function saveChange(string $changeId): array
    {
        $session = $this->requireChange($changeId);
        $status = (string) ($session['status'] ?? 'draft');

        if ($this->isSavedStatus($status)) {
            return $session;
        }
        if ($this->isDiscardedStatus($status)) {
            throw new \RuntimeException('This draft change has already been discarded.');
        }
        if ($status === 'failed') {
            throw new \RuntimeException('This draft change failed. Create a new draft change and retry.');
        }

        $session = $this->validateChange($changeId);
        if (!($session['validation']['valid'] ?? false)) {
            $this->audit('save_blocked', $session, null);
            throw new \RuntimeException('Validation failed. Save blocked.');
        }

        $projection = $this->projectedState($session);
        try {
            $this->applyProjectedState($projection);
            $session['status'] = 'saved';
            $this->changeRepository->save($session);

            $this->audit('save', $session, null);

            return $session;
        } catch (\Throwable $exception) {
            $session['status'] = 'failed';
            $session['lastError'] = $exception->getMessage();
            $this->changeRepository->save($session);
            $this->audit('save_failed', $session, null);
            throw $exception;
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function commitChange(string $changeId, string $commitMessage = '', bool $createGitCommit = true): array
    {
        return $this->saveChange($changeId);
    }

    /**
     * @return array<string,mixed>
     */
    public function discardChange(string $changeId): array
    {
        $session = $this->requireChange($changeId);
        if ($this->isDiscardedStatus((string) ($session['status'] ?? 'draft'))) {
            return $session;
        }

        $session['status'] = 'discarded';
        $this->changeRepository->save($session);

        $this->audit('abort', $session, null);

        return $session;
    }

    /**
     * @return array<string,mixed>
     */
    public function abortChange(string $changeId): array
    {
        return $this->discardChange($changeId);
    }

    /**
     * @param array<string,mixed> $projection
     * @return array<int,array<string,mixed>>
     */
    private function buildDiff(array $projection): array
    {
        $items = [];

        if (is_array($projection['projectedSettings'] ?? null)) {
            $path = 'settings.yaml';
            $after = $this->recordSerializer->encode($projection['projectedSettings'], $path);
            $before = $this->readRegistryFileIfExists($path);
            if ($before !== $after) {
                $items[] = [
                    'status' => $before === null ? 'added' : 'modified',
                    'path' => $path,
                    'recordId' => 'settings',
                    'before' => $before,
                    'after' => $after,
                ];
            }
        }

        foreach ($projection['projectedById'] as $id => $entity) {
            $path = $entity['sourcePath'];
            $after = $this->recordSerializer->encode($entity['record'], $path);
            $before = null;
            $status = 'added';

            if (isset($projection['baselineById'][$id])) {
                $baselinePath = $projection['baselineById'][$id]['sourcePath'];
                if ($baselinePath === $path) {
                    $before = $this->readFileIfExists($baselinePath, true);
                    $status = $before === $after ? 'unchanged' : 'modified';
                } else {
                    $before = $this->readFileIfExists($baselinePath, true);
                    $status = 'modified';
                }
            }

            if ($status === 'unchanged') {
                continue;
            }

            $items[] = [
                'status' => $status,
                'path' => $path,
                'recordId' => $id,
                'before' => $before,
                'after' => $after,
            ];
        }


        foreach ($projection['projectedRelationsById'] as $id => $relation) {
            if (($relation['derived'] ?? false) === true) {
                continue;
            }
            $path = $relation['sourcePath'];
            $after = $this->recordSerializer->encode($relation['record'], $path);
            $before = null;
            $status = 'added';

            if (isset($projection['baselineRelationsById'][$id])) {
                $baselinePath = $projection['baselineRelationsById'][$id]['sourcePath'];
                $before = $this->readFileIfExists($baselinePath, false);
                $status = $before === $after ? 'unchanged' : 'modified';
            }

            if ($status === 'unchanged') {
                continue;
            }

            $items[] = ['status' => $status, 'path' => $path, 'recordId' => $id, 'before' => $before, 'after' => $after];
        }

        foreach (array_merge($projection['deleteEntityPaths'], $projection['deleteRelationPaths']) as $path) {
            $items[] = [
                'status' => 'deleted',
                'path' => $path,
                'before' => $this->readFileIfExists($path, str_starts_with($path, 'entities/') || str_starts_with($path, 'resources/')),
                'after' => null,
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $a['path'], (string) $b['path']));

        return $items;
    }

    /**
     * @param array<string,mixed> $projection
     */
    private function applyProjectedState(array $projection): void
    {
        if ($projection['deleteEntityPaths'] !== []) {
            $this->entityRepository->deleteEntityPaths($projection['deleteEntityPaths']);
        }
        if ($projection['deleteRelationPaths'] !== []) {
            $this->relationRepository->deleteRelationPaths($projection['deleteRelationPaths']);
        }

        foreach ($projection['projectedById'] as $entity) {
            $this->entityRepository->writeEntity($entity['record'], $entity['sourcePath']);
        }
        foreach ($projection['projectedRelationsById'] as $relation) {
            if (($relation['derived'] ?? false) === true) {
                continue;
            }
            $this->relationRepository->writeRelation($relation['record'], $relation['sourcePath']);
        }
        if (is_array($projection['projectedSettings'] ?? null)) {
            $this->writeRegistryFile('settings.yaml', $this->recordSerializer->encode($projection['projectedSettings'], 'settings.yaml'));
        }
    }

    /**
     * @param array<string,mixed> $session
     * @return array<string,mixed>
     */
    private function projectedState(array $session): array
    {
        $index = $this->entityRepository->loadEntityIndex();

        $baselineById = $index['byId'];
        $projectedById = $baselineById;
        $finalIdPaths = $index['idPaths'];
        $relationIndex = $this->relationRepository->loadRelationIndex();

        $baselineRelationsById = $relationIndex['byId'];
        $projectedRelationsById = $baselineRelationsById;
        $finalRelationPaths = $relationIndex['idPaths'];

        $errors = [];
        foreach ($index['parseErrors'] ?? [] as $parseError) {
            $path = (string) ($parseError['path'] ?? '');
            $message = (string) ($parseError['message'] ?? 'Invalid entity record.');
            $errors[] = trim($path . ': ' . $message);
        }
        $deleteEntityPaths = [];
        $deleteRelationPaths = [];
        $projectedSettings = null;

        foreach ($session['operations'] ?? [] as $operation) {
            $type = (string) ($operation['type'] ?? '');

            if ($type === 'upsert_entity') {
                $entity = is_array($operation['entity'] ?? null) ? $operation['entity'] : null;
                if ($entity === null) {
                    $errors[] = 'upsert_entity requires an entity object.';
                    continue;
                }

                $metadata = is_array($entity['metadata'] ?? null) ? $entity['metadata'] : [];
                $id = (string) ($metadata['id'] ?? '');
                if ($id === '') {
                    $errors[] = 'upsert_entity requires entity.metadata.id.';
                    continue;
                }

                $sourcePath = (string) ($operation['sourcePath'] ?? '');
                if ($sourcePath === '' && isset($baselineById[$id])) {
                    $sourcePath = (string) $baselineById[$id]['sourcePath'];
                }
                if ($sourcePath === '' || str_starts_with($sourcePath, 'entities/')) {
                    $sourcePath = $this->defaultEntityPathForId($id);
                }

                try {
                    $normalizedPath = $this->pathGuard->normalizeEntityPath($sourcePath);
                } catch (\Throwable $exception) {
                    $errors[] = $exception->getMessage();
                    continue;
                }

                if (isset($finalIdPaths[$id])) {
                    foreach ($finalIdPaths[$id] as $existingPath) {
                        if ($existingPath !== $normalizedPath) {
                            $deleteEntityPaths[] = $existingPath;
                        }
                    }
                }

                $projectedById[$id] = [
                    'record' => $entity,
                    'sourcePath' => $normalizedPath,
                ];
                $finalIdPaths[$id] = [$normalizedPath];
                continue;
            }

            if ($type === 'delete_entity') {
                $id = (string) ($operation['id'] ?? '');
                if ($id === '') {
                    $errors[] = 'delete_entity requires id.';
                    continue;
                }

                if (isset($finalIdPaths[$id])) {
                    foreach ($finalIdPaths[$id] as $existingPath) {
                        $deleteEntityPaths[] = $existingPath;
                    }
                }

                unset($projectedById[$id], $finalIdPaths[$id]);
                continue;
            }


            if ($type === 'upsert_relation') {
                $relation = is_array($operation['relation'] ?? null) ? $operation['relation'] : [];
                $metadata = is_array($relation['metadata'] ?? null) ? $relation['metadata'] : [];
                $id = is_string($metadata['id'] ?? null) ? (string) $metadata['id'] : '';
                if ($id === '') {
                    $errors[] = 'upsert_relation requires relation.metadata.id.';
                    continue;
                }

                $sourcePath = (string) ($operation['sourcePath'] ?? '');
                if ($sourcePath === '' && isset($baselineRelationsById[$id])) {
                    $sourcePath = (string) $baselineRelationsById[$id]['sourcePath'];
                }
                if ($sourcePath === '') {
                    $sourcePath = $this->defaultRelationPathForId($id);
                }

                try {
                    $normalizedPath = $this->pathGuard->normalizeWithinRegistry($sourcePath, 'relations');
                } catch (\Throwable $exception) {
                    $errors[] = $exception->getMessage();
                    continue;
                }

                if (isset($finalRelationPaths[$id])) {
                    foreach ($finalRelationPaths[$id] as $existingPath) {
                        if ($existingPath !== $normalizedPath) {
                            $deleteRelationPaths[] = $existingPath;
                        }
                    }
                }

                $projectedRelationsById[$id] = [
                    'record' => $relation,
                    'sourcePath' => $normalizedPath,
                ];
                $finalRelationPaths[$id] = [$normalizedPath];
                continue;
            }

            if ($type === 'delete_relation') {
                $id = (string) ($operation['id'] ?? '');
                if ($id === '') {
                    $errors[] = 'delete_relation requires id.';
                    continue;
                }

                if (isset($finalRelationPaths[$id])) {
                    foreach ($finalRelationPaths[$id] as $existingPath) {
                        $deleteRelationPaths[] = $existingPath;
                    }
                }

                unset($projectedRelationsById[$id], $finalRelationPaths[$id]);
                continue;
            }

            if ($type === 'set_dependency_slot') {
                $resourceId = (string) ($operation['resourceId'] ?? '');
                $slotKey = (string) ($operation['slot'] ?? '');
                $targets = is_array($operation['targets'] ?? null) ? $operation['targets'] : [];
                if ($resourceId === '' || $slotKey === '') {
                    $errors[] = 'set_dependency_slot requires resourceId and slot.';
                    continue;
                }
                if (!isset($projectedById[$resourceId])) {
                    $errors[] = 'set_dependency_slot references missing resource: ' . $resourceId;
                    continue;
                }

                $record = is_array($projectedById[$resourceId]['record'] ?? null) ? $projectedById[$resourceId]['record'] : [];
                $dependencies = is_array($record['dependencies'] ?? null) ? $record['dependencies'] : [];
                $normalizedTargets = [];
                foreach ($targets as $target) {
                    if (!is_scalar($target) && $target !== null) {
                        continue;
                    }
                    $targetId = trim((string) ($target ?? ''));
                    if ($targetId === '') {
                        continue;
                    }
                    $normalizedTargets[] = $targetId;
                }
                $normalizedTargets = array_values(array_unique($normalizedTargets));
                if ($normalizedTargets === []) {
                    unset($dependencies[$slotKey]);
                } else {
                    $dependencies[$slotKey] = $normalizedTargets;
                }
                ksort($dependencies);
                $record['dependencies'] = $dependencies;
                $projectedById[$resourceId]['record'] = $record;
                continue;
            }

            if ($type === 'upsert_settings') {
                $settings = is_array($operation['settings'] ?? null) ? $operation['settings'] : null;
                if ($settings === null) {
                    $errors[] = 'upsert_settings requires settings object.';
                    continue;
                }
                $projectedSettings = $this->normalizeSettingsRecord($settings);
                continue;
            }

            $errors[] = 'Unsupported operation in change session: ' . $type;
        }

        foreach ($projectedById as $entityId => $entity) {
            $sourcePath = (string) ($entity['sourcePath'] ?? '');
            if (!str_starts_with($sourcePath, 'entities/')) {
                continue;
            }
            $newPath = $this->defaultEntityPathForId((string) $entityId);
            $deleteEntityPaths[] = $sourcePath;
            $projectedById[$entityId]['sourcePath'] = $newPath;
            $finalIdPaths[$entityId] = [$newPath];
        }

        $projectedRelationsById = array_filter(
            $projectedRelationsById,
            static fn (array $relation): bool => ($relation['derived'] ?? false) !== true
        );
        $finalRelationPaths = array_filter(
            $finalRelationPaths,
            static fn (array $paths): bool => !isset($paths[0]) || !str_starts_with((string) $paths[0], 'resources/')
        );
        foreach ($this->derivedRelationsFromProjectedEntities($projectedById) as $derivedRelation) {
            $derivedId = (string) ($derivedRelation['record']['metadata']['id'] ?? '');
            if ($derivedId === '') {
                continue;
            }
            if (isset($projectedRelationsById[$derivedId])) {
                $derivedId .= '-resource';
                $derivedRelation['record']['metadata']['id'] = $derivedId;
            }
            $projectedRelationsById[$derivedId] = $derivedRelation;
            $finalRelationPaths[$derivedId] = [(string) ($derivedRelation['sourcePath'] ?? '')];
        }

        $deleteEntityPaths = array_values(array_unique($deleteEntityPaths));
        $deleteRelationPaths = array_values(array_unique(array_filter(
            $deleteRelationPaths,
            static fn (string $path): bool => str_starts_with($path, 'relations/')
        )));

        return [
            'baselineById' => $baselineById,
            'baselineRelationsById' => $baselineRelationsById,
            'projectedById' => $projectedById,
            'finalIdPaths' => $finalIdPaths,
            'projectedRelationsById' => $projectedRelationsById,
            'finalRelationPaths' => $finalRelationPaths,
            'errors' => $errors,
            'deleteEntityPaths' => $deleteEntityPaths,
            'deleteRelationPaths' => $deleteRelationPaths,
            'projectedSettings' => $projectedSettings,
        ];
    }

    private function readFileIfExists(string $relativePath, bool $entity = true): ?string
    {
        $absolute = $entity ? $this->entityRepository->absolutePathForEntity($relativePath) : $this->relationRepository->absolutePathForRelation($relativePath);

        if (!is_file($absolute)) {
            return null;
        }

        $content = file_get_contents($absolute);

        return $content === false ? null : $content;
    }

    private function readRegistryFileIfExists(string $relativePath): ?string
    {
        return $this->registryFileRepository->read($relativePath);
    }

    private function writeRegistryFile(string $relativePath, string $content): void
    {
        $this->registryFileRepository->write($relativePath, $content);
    }

    /**
     * @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    private function normalizeSettingsRecord(array $settings): array
    {
        $tagKeys = [];
        foreach (($settings['tag_keys'] ?? []) as $key => $row) {
            $tagKey = trim((string) $key);
            if ($tagKey === '' || !is_array($row)) {
                continue;
            }
            $entry = [
                'label' => trim((string) ($row['label'] ?? $tagKey)),
            ];
            if ((bool) ($row['required'] ?? false)) {
                $entry['required'] = true;
            }
            $values = is_array($row['values'] ?? null)
                ? array_values(array_unique(array_filter(array_map('strval', $row['values']), static fn (string $v): bool => trim($v) !== '')))
                : [];
            if ($values !== []) {
                $entry['values'] = $values;
            }
            if ((bool) ($row['free_value'] ?? false) || $values === []) {
                $entry['free_value'] = true;
            }
            if ((bool) ($row['allow_empty'] ?? false)) {
                $entry['allow_empty'] = true;
            }
            $tagKeys[$tagKey] = $entry;
        }
        ksort($tagKeys);

        $reservedPrefixes = is_array($settings['reserved_prefixes'] ?? null) ? $settings['reserved_prefixes'] : ['cataloga:'];
        $reservedPrefixes = array_values(array_unique(array_filter(array_map(
            static fn (mixed $prefix): string => str_ends_with(trim((string) $prefix), ':') ? trim((string) $prefix) : trim((string) $prefix) . ':',
            $reservedPrefixes
        ), static fn (string $prefix): bool => $prefix !== ':')));

        return [
            'version' => (int) ($settings['version'] ?? 1),
            'tag_keys' => $tagKeys,
            'reserved_prefixes' => $reservedPrefixes !== [] ? $reservedPrefixes : ['cataloga:'],
        ];
    }

    private function defaultEntityPathForId(string $id): string
    {
        $slug = strtolower($id);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim((string) $slug, '-');

        if ($slug === '') {
            $slug = 'entity';
        }

        $type = 'resource';
        if (str_contains($id, '.')) {
            $typePart = strstr($id, '.', true);
            if (is_string($typePart) && $typePart !== '') {
                $type = $typePart;
            }
        }

        return 'resources/' . $type . '/' . $slug . '.yaml';
    }


    private function defaultRelationPathForId(string $id): string
    {
        $slug = strtolower($id);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim((string) $slug, '-');

        if ($slug === '') {
            $slug = 'relation';
        }

        return 'relations/relation-' . $slug . '.yaml';
    }

    /**
     * @param array<string,array<string,mixed>> $entitiesById
     * @return array<int,array{record:array<string,mixed>,sourcePath:string,derived:bool}>
     */
    private function derivedRelationsFromProjectedEntities(array $entitiesById): array
    {
        $resources = [];
        foreach ($entitiesById as $entity) {
            $record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
            $resources[] = [
                'record' => $record,
                'sourcePath' => (string) ($entity['sourcePath'] ?? ''),
            ];
        }

        return $this->dependencyProjector->project($resources);
    }

    /**
     * @return array<string,mixed>
     */
    private function requireChange(string $changeId): array
    {
        $session = $this->changeRepository->get($changeId);
        if ($session === null) {
            throw new \RuntimeException('Change session not found: ' . $changeId);
        }

        return $session;
    }

    private function normalizeActorType(string $actorType): string
    {
        $allowed = ['human', 'agent', 'cli', 'unknown'];

        return in_array($actorType, $allowed, true) ? $actorType : 'unknown';
    }

    /**
     * @param array<string,mixed> $session
     */
    private function audit(string $event, array $session, ?string $commitHash): void
    {
        $operations = is_array($session['operations'] ?? null) ? $session['operations'] : [];
        $operationTypes = [];
        $targetIds = [];

        foreach ($operations as $operation) {
            if (!is_array($operation)) {
                continue;
            }
            $operationTypes[] = (string) ($operation['type'] ?? '');

            if (($operation['type'] ?? null) === 'delete_entity' && is_string($operation['id'] ?? null)) {
                $targetIds[] = (string) $operation['id'];
            }
            if (($operation['type'] ?? null) === 'upsert_entity') {
                $metadata = is_array($operation['entity']['metadata'] ?? null) ? $operation['entity']['metadata'] : [];
                if (is_string($metadata['id'] ?? null)) {
                    $targetIds[] = (string) $metadata['id'];
                }
            }
            if (($operation['type'] ?? null) === 'delete_relation' && is_string($operation['id'] ?? null)) {
                $targetIds[] = (string) $operation['id'];
            }
            if (($operation['type'] ?? null) === 'upsert_relation') {
                $relation = is_array($operation['relation'] ?? null) ? $operation['relation'] : $operation;
                $metadata = is_array($relation['metadata'] ?? null) ? $relation['metadata'] : [];
                if (is_string($metadata['id'] ?? null)) {
                    $targetIds[] = (string) $metadata['id'];
                } elseif (is_string($relation['id'] ?? null)) {
                    $targetIds[] = (string) $relation['id'];
                }
            }
        }

        $this->auditLogger->append([
            'timestamp' => gmdate(DATE_ATOM),
            'event' => $event,
            'actor' => (string) ($session['actor'] ?? 'unknown'),
            'actorType' => (string) ($session['actorType'] ?? 'unknown'),
            'changeId' => (string) ($session['id'] ?? ''),
            'operationTypes' => array_values(array_unique($operationTypes)),
            'targetIds' => array_values(array_unique($targetIds)),
            'validation' => $session['validation'] ?? null,
            'commitHash' => $commitHash,
        ]);
    }
}
