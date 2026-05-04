<?php

declare(strict_types=1);

namespace Cataloga\Mutation;

use Cataloga\Audit\AuditLogger;
use Cataloga\Git\GitService;
use Cataloga\Registry\EntityRepository;
use Cataloga\Registry\RelationRepository;
use Cataloga\Registry\PathGuard;
use Cataloga\Registry\RecordSerializer;
use Cataloga\Validation\RegistryValidator;

final class ChangeService
{
    private const ALLOWED_OPERATIONS = ['upsert_entity', 'delete_entity', 'upsert_relation', 'delete_relation'];

    public function __construct(
        private readonly EntityRepository $entityRepository,
        private readonly RelationRepository $relationRepository,
        private readonly RecordSerializer $recordSerializer,
        private readonly PathGuard $pathGuard,
        private readonly ChangeSessionRepository $changeRepository,
        private readonly RegistryValidator $validator,
        private readonly GitService $gitService,
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
        return $this->changeRepository->get($id);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listRecentChanges(int $limit = 20): array
    {
        return $this->changeRepository->listRecent($limit);
    }

    /**
     * @param array<int,array<string,mixed>>|array<string,mixed> $operations
     * @return array<string,mixed>
     */
    public function addOperations(string $changeId, array $operations): array
    {
        $session = $this->requireChange($changeId);
        if (in_array($session['status'], ['committed', 'aborted'], true)) {
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

        $session['status'] = 'open';
        $this->changeRepository->save($session);

        return $session;
    }

    /**
     * @return array<string,mixed>
     */
    public function validateChange(string $changeId): array
    {
        $session = $this->requireChange($changeId);
        if (in_array($session['status'], ['committed', 'aborted'], true)) {
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
        $session['status'] = $validation['valid'] ? 'validated' : 'open';
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
    public function commitChange(string $changeId, string $commitMessage = '', bool $createGitCommit = true): array
    {
        $session = $this->requireChange($changeId);
        if (in_array($session['status'], ['committed', 'aborted'], true)) {
            throw new \RuntimeException('Cannot commit closed change session.');
        }

        $session = $this->validateChange($changeId);
        if (!($session['validation']['valid'] ?? false)) {
            $this->audit('commit_blocked', $session, null);
            throw new \RuntimeException('Validation failed. Commit blocked.');
        }

        $projection = $this->projectedState($session);
        try {
            $this->applyProjectedState($projection);

            $commitHash = null;
            $git = [
                'enabled' => $createGitCommit,
                'message' => '',
            ];

            if ($createGitCommit) {
                $message = trim($commitMessage) !== '' ? trim($commitMessage) : 'Cataloga change ' . $session['id'];
                $addResult = $this->gitService->addRegistry();

                if (!$addResult['ok']) {
                    $git['message'] = $addResult['stderr'] ?: 'git add failed.';
                } else {
                    $commitResult = $this->gitService->commit($message);
                    if (!$commitResult['ok']) {
                        $git['message'] = $commitResult['stderr'] ?: 'git commit failed.';
                    } else {
                        $head = $this->gitService->revParseHead();
                        if ($head['ok']) {
                            $commitHash = $head['stdout'];
                        }
                    }
                }
            }

            $session['status'] = 'committed';
            $session['commitHash'] = $commitHash;
            $session['git'] = $git;
            $this->changeRepository->save($session);

            $this->audit('commit', $session, $commitHash);

            return $session;
        } catch (\Throwable $exception) {
            $session['lastError'] = $exception->getMessage();
            $this->changeRepository->save($session);
            $this->audit('commit_failed', $session, null);
            throw $exception;
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function abortChange(string $changeId): array
    {
        $session = $this->requireChange($changeId);
        if (in_array($session['status'], ['committed', 'aborted'], true)) {
            return $session;
        }

        $session['status'] = 'aborted';
        $this->changeRepository->save($session);

        $this->audit('abort', $session, null);

        return $session;
    }

    /**
     * @param array<string,mixed> $projection
     * @return array<int,array<string,mixed>>
     */
    private function buildDiff(array $projection): array
    {
        $items = [];

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
                'before' => $this->readFileIfExists($path, str_starts_with($path, 'entities/')),
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
            $this->relationRepository->writeRelation($relation['record'], $relation['sourcePath']);
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
                if ($sourcePath === '') {
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
                $spec = is_array($relation['spec'] ?? null) ? $relation['spec'] : [];

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

            $errors[] = 'Unsupported operation in change session: ' . $type;
        }

        $deleteEntityPaths = array_values(array_unique($deleteEntityPaths));
        $deleteRelationPaths = array_values(array_unique($deleteRelationPaths));

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

    private function defaultEntityPathForId(string $id): string
    {
        $slug = strtolower($id);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim((string) $slug, '-');

        if ($slug === '') {
            $slug = 'entity';
        }

        return 'entities/entity-' . $slug . '.yaml';
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
                if (is_string($relation['id'] ?? null)) {
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
