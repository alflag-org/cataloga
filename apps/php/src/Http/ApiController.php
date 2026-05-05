<?php

declare(strict_types=1);

namespace Cataloga\Http;

use Cataloga\Mutation\ChangeService;
use Cataloga\Registry\DomainPackRepository;
use Cataloga\Registry\EntityRepository;
use Cataloga\Registry\RegistrySettingsRepository;
use Cataloga\Registry\RelationRepository;
use Cataloga\Registry\SchemaRepository;

final class ApiController
{
    public function __construct(
        private readonly EntityRepository $entityRepository,
        private readonly RelationRepository $relationRepository,
        private readonly DomainPackRepository $domainPackRepository,
        private readonly SchemaRepository $schemaRepository,
        private readonly RegistrySettingsRepository $settingsRepository,
        private readonly ChangeService $changeService,
    ) {
    }

    public function relations(Request $request): Response
    {
        return Response::json([
            'items' => $this->relationRepository->listRelations(),
        ]);
    }

    public function dependencies(Request $request): Response
    {
        return Response::json([
            'items' => $this->relationRepository->listRelations(),
        ]);
    }

    public function entities(Request $request): Response
    {
        return Response::json([
            'items' => $this->entityRepository->listEntities(),
        ]);
    }

    public function resources(Request $request): Response
    {
        return Response::json([
            'items' => $this->entityRepository->listEntities(),
        ]);
    }

    public function domainPacks(Request $request): Response
    {
        return Response::json([
            'items' => $this->domainPackRepository->listDomainPacks(),
        ]);
    }

    public function typePacks(Request $request): Response
    {
        return Response::json([
            'items' => $this->domainPackRepository->listDomainPacks(),
        ]);
    }

    public function installedTypePacks(Request $request): Response
    {
        return Response::json([
            'items' => $this->domainPackRepository->listInstalledPacks(),
        ]);
    }

    public function availableTypePacks(Request $request): Response
    {
        return Response::json([
            'items' => $this->domainPackRepository->listAvailablePacks(),
        ]);
    }

    /**
     * @param array<string,string> $params
     */
    public function entity(Request $request, array $params): Response
    {
        $id = $params['id'] ?? '';
        $entity = $this->entityRepository->getEntity($id);
        if ($entity === null) {
            return Response::json(['error' => 'Resource not found'], 404);
        }

        return Response::json($entity);
    }

    /**
     * @param array<string,string> $params
     */
    public function resource(Request $request, array $params): Response
    {
        return $this->entity($request, $params);
    }

    public function schemas(Request $request): Response
    {
        $items = $this->schemaRepository->listSchemas();

        return Response::json(['items' => $items, 'counts' => ['items' => count($items)]]);
    }

    public function settings(Request $request): Response
    {
        return Response::json($this->settingsRepository->loadSettings());
    }

    public function tagKeys(Request $request): Response
    {
        $settings = $this->settingsRepository->loadSettings();
        $tagKeys = is_array($settings['tag_keys'] ?? null) ? $settings['tag_keys'] : [];
        $reservedPrefixes = is_array($settings['reserved_prefixes'] ?? null) ? $settings['reserved_prefixes'] : [];

        return Response::json([
            'items' => $tagKeys,
            'reserved_prefixes' => $reservedPrefixes,
        ]);
    }

    public function types(Request $request): Response
    {
        $schemas = $this->activeSchemas();
        $resourceTypes = [];
        $dependencyTypes = [];

        foreach ($schemas as $schema) {
            $entry = [
                'id' => (string) ($schema['id'] ?? ''),
                'name' => (string) ($schema['name'] ?? ''),
                'description' => (string) ($schema['description'] ?? ''),
                'source' => (string) ($schema['source'] ?? ''),
                'sourcePath' => (string) ($schema['sourcePath'] ?? ''),
            ];
            if (($schema['kind'] ?? 'entity') === 'relation') {
                $dependencyTypes[] = $entry;
            } else {
                $resourceTypes[] = $entry;
            }
        }

        return Response::json([
            'resource_types' => $resourceTypes,
            'dependency_types' => $dependencyTypes,
            'counts' => [
                'resource_types' => count($resourceTypes),
                'dependency_types' => count($dependencyTypes),
            ],
        ]);
    }

    /**
     * @param array<string,string> $params
     */
    public function entityNeighbors(Request $request, array $params): Response
    {
        $id = $params['id'] ?? '';
        $entity = $this->entityRepository->getEntity($id);
        if ($entity === null) {
            return Response::json(['error' => 'Resource not found', 'id' => $id], 404);
        }

        $relations = $this->relationRepository->listRelations();
        $neighbors = [];
        $relatedRelations = [];

        foreach ($relations as $relation) {
            $from = (string) ($relation['from'] ?? '');
            $to = (string) ($relation['to'] ?? '');
            if ($from !== $id && $to !== $id) {
                continue;
            }

            $neighborId = $from === $id ? $to : $from;
            if ($neighborId !== '') {
                $neighborEntity = $this->entityRepository->getEntity($neighborId);
                $neighborMetadata = is_array($neighborEntity['record']['metadata'] ?? null) ? $neighborEntity['record']['metadata'] : [];
                $neighbors[] = [
                    'id' => $neighborId,
                    'type' => (string) ($neighborMetadata['type'] ?? ''),
                    'path' => $neighborEntity['sourcePath'] ?? null,
                    'exists' => $neighborEntity !== null,
                ];
            }

            $relatedRelations[] = [
                'id' => (string) ($relation['id'] ?? ''),
                'type' => (string) ($relation['type'] ?? ''),
                'path' => (string) ($relation['sourcePath'] ?? ''),
                'from' => $from,
                'to' => $to,
            ];
        }

        usort($neighbors, static fn (array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']));

        return Response::json([
            'id' => $id,
            'type' => (string) (($entity['record']['metadata']['type'] ?? '')),
            'path' => $entity['sourcePath'] ?? null,
            'neighbors' => $neighbors,
            'relations' => $relatedRelations,
            'counts' => ['neighbors' => count($neighbors), 'relations' => count($relatedRelations)],
        ]);
    }

    /**
     * @param array<string,string> $params
     */
    public function graph(Request $request, array $params = []): Response
    {
        $resourceId = trim((string) $request->query('resource', ''));
        if ($resourceId !== '') {
            return $this->entityNeighbors($request, ['id' => $resourceId]);
        }

        return Response::json([
            'resources' => $this->entityRepository->listEntities(),
            'dependencies' => $this->relationRepository->listRelations(),
        ]);
    }

    public function search(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $needle = strtolower($query);
        $items = [];

        foreach ($this->entityRepository->listEntities() as $entity) {
            $record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
            $haystack = strtolower(json_encode($entity) ?: '');
            if ($needle !== '' && !str_contains($haystack, $needle)) {
                continue;
            }
            $items[] = [
                'kind' => 'Resource',
                'id' => (string) ($metadata['id'] ?? ''),
                'type' => (string) ($metadata['type'] ?? ''),
                'name' => (string) ($metadata['name'] ?? ''),
                'path' => (string) ($entity['sourcePath'] ?? ''),
            ];
        }

        foreach ($this->relationRepository->listRelations() as $relation) {
            $haystack = strtolower(json_encode($relation) ?: '');
            if ($needle !== '' && !str_contains($haystack, $needle)) {
                continue;
            }
            $items[] = [
                'kind' => 'Dependency',
                'id' => (string) ($relation['id'] ?? ''),
                'type' => (string) ($relation['type'] ?? ''),
                'name' => (string) (($relation['record']['metadata']['name'] ?? '')),
                'path' => (string) ($relation['sourcePath'] ?? ''),
                'from' => (string) ($relation['from'] ?? ''),
                'to' => (string) ($relation['to'] ?? ''),
            ];
        }

        return Response::json(['query' => $query, 'items' => $items, 'counts' => ['items' => count($items)]]);
    }

    /**
     * @param array<string,string> $params
     */
    public function resourceDependencySlots(Request $request, array $params): Response
    {
        $id = $params['id'] ?? '';
        $entity = $this->entityRepository->getEntity($id);
        if ($entity === null) {
            return Response::json(['error' => 'Resource not found'], 404);
        }

        $record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
        $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
        $resourceType = (string) ($metadata['type'] ?? '');
        if ($resourceType === '') {
            return Response::json(['id' => $id, 'type' => '', 'slots' => []]);
        }

        $schemas = $this->activeSchemas();
        $resourceSchema = null;
        foreach ($schemas as $schema) {
            if (($schema['kind'] ?? 'entity') !== 'entity') {
                continue;
            }
            if ((string) ($schema['id'] ?? '') !== $resourceType) {
                continue;
            }
            $resourceSchema = $schema;
            break;
        }

        $slots = is_array($resourceSchema['dependencySlots'] ?? null) ? $resourceSchema['dependencySlots'] : [];

        return Response::json([
            'id' => $id,
            'type' => $resourceType,
            'slots' => $slots,
        ]);
    }

    /**
     * @param array<string,string> $params
     */
    public function changeSummary(Request $request, array $params): Response
    {
        $id = $params['id'] ?? '';
        $session = $this->changeService->getChange($id);
        if ($session === null) {
            return Response::json(['error' => 'Draft change not found', 'id' => $id], 404);
        }

        $operations = is_array($session['operations'] ?? null) ? $session['operations'] : [];
        $validation = is_array($session['validation'] ?? null) ? $session['validation'] : [];
        $errors = is_array($validation['errors'] ?? null) ? $validation['errors'] : [];

        return Response::json([
            'id' => (string) ($session['id'] ?? $id),
            'type' => 'draft_change',
            'status' => (string) ($session['status'] ?? 'unknown'),
            'path' => $session['stagingPath'] ?? null,
            'counts' => [
                'operations' => count($operations),
                'errors' => count($errors),
            ],
            'errors' => $errors,
        ]);
    }

    public function createChange(Request $request): Response
    {
        try {
            $payload = $request->all();
            $actor = (string) ($payload['actor'] ?? 'unknown');
            $actorType = (string) ($payload['actorType'] ?? 'unknown');
            $session = $this->changeService->createChange($actor, $actorType);

            return Response::json($session, 201);
        } catch (\Throwable $exception) {
            return Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    /**
     * @param array<string,string> $params
     */
    public function getChange(Request $request, array $params): Response
    {
        $id = $params['id'] ?? '';
        $session = $this->changeService->getChange($id);

        if ($session === null) {
            return Response::json(['error' => 'Draft change not found'], 404);
        }

        return Response::json($session);
    }

    /**
     * @param array<string,string> $params
     */
    public function addOperations(Request $request, array $params): Response
    {
        try {
            $id = $params['id'] ?? '';
            $payload = $request->all();
            $operations = $payload['operations'] ?? $payload;
            if (!is_array($operations)) {
                return Response::json(['error' => 'operations must be an object or array'], 422);
            }

            $session = $this->changeService->addOperations($id, $operations);

            return Response::json($session);
        } catch (\Throwable $exception) {
            return Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    /**
     * @param array<string,string> $params
     */
    public function addEdits(Request $request, array $params): Response
    {
        return $this->addOperations($request, $params);
    }

    /**
     * @param array<string,string> $params
     */
    public function validateChange(Request $request, array $params): Response
    {
        try {
            $id = $params['id'] ?? '';
            $session = $this->changeService->validateChange($id);

            return Response::json($session);
        } catch (\Throwable $exception) {
            return Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    /**
     * @param array<string,string> $params
     */
    public function diffChange(Request $request, array $params): Response
    {
        try {
            $id = $params['id'] ?? '';

            return Response::json($this->changeService->diffChange($id));
        } catch (\Throwable $exception) {
            return Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    /**
     * @param array<string,string> $params
     */
    public function commitChange(Request $request, array $params): Response
    {
        try {
            $id = $params['id'] ?? '';
            $payload = $request->all();
            $message = (string) ($payload['commitMessage'] ?? '');
            $createGitCommit = $this->parseBoolean($payload['createGitCommit'] ?? false);

            $session = $this->changeService->commitChange($id, $message, $createGitCommit);

            return Response::json($session);
        } catch (\Throwable $exception) {
            return Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    /**
     * @param array<string,string> $params
     */
    public function saveChange(Request $request, array $params): Response
    {
        return $this->commitChange($request, $params);
    }

    /**
     * @param array<string,string> $params
     */
    public function abortChange(Request $request, array $params): Response
    {
        try {
            $id = $params['id'] ?? '';
            $session = $this->changeService->abortChange($id);

            return Response::json($session);
        } catch (\Throwable $exception) {
            return Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    /**
     * @param array<string,string> $params
     */
    public function discardChange(Request $request, array $params): Response
    {
        return $this->abortChange($request, $params);
    }

    public function installTypePack(Request $request): Response
    {
        try {
            $name = trim((string) ($request->all()['name'] ?? ''));
            if ($name === '') {
                return Response::json(['error' => 'name is required'], 422);
            }

            return Response::json(['item' => $this->domainPackRepository->installPack($name)]);
        } catch (\Throwable $exception) {
            return Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    /**
     * @param array<string,string> $params
     */
    public function enableTypePack(Request $request, array $params): Response
    {
        try {
            $name = trim((string) ($params['name'] ?? ''));
            return Response::json(['item' => $this->domainPackRepository->enablePack($name)]);
        } catch (\Throwable $exception) {
            return Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    /**
     * @param array<string,string> $params
     */
    public function disableTypePack(Request $request, array $params): Response
    {
        try {
            $name = trim((string) ($params['name'] ?? ''));
            return Response::json(['item' => $this->domainPackRepository->disablePack($name)]);
        } catch (\Throwable $exception) {
            return Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    /**
     * @param array<string,string> $params
     */
    public function uninstallTypePack(Request $request, array $params): Response
    {
        try {
            $name = trim((string) ($params['name'] ?? ''));
            return Response::json(['item' => $this->domainPackRepository->uninstallPack($name)]);
        } catch (\Throwable $exception) {
            return Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function activeSchemas(): array
    {
        $schemas = $this->schemaRepository->listSchemas();
        $packState = [];
        foreach ($this->domainPackRepository->listDomainPacks() as $pack) {
            $packId = (string) ($pack['id'] ?? '');
            if ($packId === '') {
                continue;
            }
            $packState[$packId] = (bool) (($pack['installed'] ?? false) && ($pack['enabled'] ?? false));
        }

        $active = [];
        foreach ($schemas as $schema) {
            $source = (string) ($schema['source'] ?? '');
            $sourcePath = (string) ($schema['sourcePath'] ?? '');
            if ($source !== 'domain-pack') {
                $active[] = $schema;
                continue;
            }

            if (preg_match('#^domain-packs/([^/]+)/#', $sourcePath, $matches) !== 1) {
                continue;
            }
            $packId = (string) ($matches[1] ?? '');
            if (($packState[$packId] ?? false) !== true) {
                continue;
            }
            $active[] = $schema;
        }

        return $active;
    }
}
