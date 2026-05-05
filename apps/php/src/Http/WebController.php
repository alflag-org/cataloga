<?php

declare(strict_types=1);

namespace Cataloga\Http;

use Cataloga\Git\GitService;
use Cataloga\Mutation\ChangeService;
use Cataloga\Registry\DomainPackRepository;
use Cataloga\Registry\EntityRepository;
use Cataloga\Registry\RegistrySettingsRepository;
use Cataloga\Registry\RelationRepository;
use Cataloga\Registry\SchemaRepository;
use Cataloga\View\TemplateRenderer;

final class WebController
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly EntityRepository $entityRepository,
        private readonly RelationRepository $relationRepository,
        private readonly DomainPackRepository $domainPackRepository,
        private readonly SchemaRepository $schemaRepository,
        private readonly RegistrySettingsRepository $settingsRepository,
        private readonly ChangeService $changeService,
        private readonly GitService $gitService,
    ) {
    }

    public function dashboard(Request $request): Response
    {
        $resources = $this->entityRepository->listEntities();
        $dependencies = $this->relationRepository->listRelations();
        $typePacks = $this->domainPackRepository->listDomainPacks();
        $changes = $this->changeService->listRecentChanges(10);

        $draftCount = 0;
        foreach ($changes as $change) {
            $status = (string) ($change['status'] ?? 'open');
            if (!in_array($status, ['applied', 'committed', 'failed', 'discarded', 'aborted'], true)) {
                $draftCount++;
            }
        }

        $recentResources = array_slice($resources, 0, 8);
        $recentChanges = array_slice($changes, 0, 8);
        $warningCount = 0;
        foreach ($recentChanges as $change) {
            $validation = is_array($change['validation'] ?? null) ? $change['validation'] : [];
            $errors = is_array($validation['errors'] ?? null) ? $validation['errors'] : [];
            $warnings = is_array($validation['warnings'] ?? null) ? $validation['warnings'] : [];
            if ($errors !== [] || $warnings !== []) {
                $warningCount++;
            }
        }

        $gitStatus = $this->gitService->statusShort();

        $html = $this->renderer->render('dashboard', [
            'title' => 'ダッシュボード',
            'currentPath' => '/',
            'resourceCount' => count($resources),
            'dependencyCount' => count($dependencies),
            'typePackCount' => count($typePacks),
            'draftCount' => $draftCount,
            'warningCount' => $warningCount,
            'recentResources' => $recentResources,
            'recentChanges' => $recentChanges,
            'gitStatus' => $gitStatus,
        ]);

        return Response::html($html);
    }

    public function graphPage(Request $request): Response
    {
        $resources = $this->entityRepository->listEntities();
        $dependencies = $this->relationRepository->listRelations();

        $typeCounts = [];
        foreach ($resources as $resource) {
            $type = (string) ($resource['type'] ?? '');
            $key = $type !== '' ? $type : 'unknown';
            $typeCounts[$key] = (int) ($typeCounts[$key] ?? 0) + 1;
        }
        arsort($typeCounts);

        $html = $this->renderer->render('graph/index', [
            'title' => 'グラフ',
            'currentPath' => '/graph',
            'entities' => $resources,
            'relations' => $dependencies,
            'typeCounts' => $typeCounts,
        ]);

        return Response::html($html);
    }

    public function entityList(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));
        $environmentFilter = trim((string) $request->query('environment', ''));
        $ownerFilter = trim((string) $request->query('owner', ''));
        $siteFilter = trim((string) $request->query('site', ''));
        $zoneFilter = trim((string) $request->query('zone', ''));
        $lifecycleFilter = trim((string) $request->query('lifecycle', ''));

        $entities = $this->entityRepository->listEntities();
        $filtered = [];
        $types = [];
        $settings = $this->settingsRepository->loadSettings();
        $tagKeys = is_array($settings['tag_keys'] ?? null) ? $settings['tag_keys'] : [];

        foreach ($entities as $entity) {
            $record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
            $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
            $normalizedTags = $this->normalizedTagsForRecord($record);

            $resourceType = (string) ($entity['type'] ?? '');
            if ($resourceType !== '') {
                $types[$resourceType] = true;
            }
            if ($type !== '' && $resourceType !== $type) {
                continue;
            }

            $haystack = strtolower(json_encode([$entity, $metadata, $spec], JSON_UNESCAPED_UNICODE) ?: '');
            if ($query !== '' && !str_contains($haystack, strtolower($query))) {
                continue;
            }
            if ($environmentFilter !== '' && (string) ($normalizedTags['environment'] ?? '') !== $environmentFilter) {
                continue;
            }
            if ($ownerFilter !== '' && (string) ($normalizedTags['owner'] ?? '') !== $ownerFilter) {
                continue;
            }
            if ($siteFilter !== '' && (string) ($normalizedTags['site'] ?? '') !== $siteFilter) {
                continue;
            }
            if ($zoneFilter !== '' && (string) ($normalizedTags['zone'] ?? '') !== $zoneFilter) {
                continue;
            }
            if ($lifecycleFilter !== '' && (string) ($normalizedTags['lifecycle'] ?? '') !== $lifecycleFilter) {
                continue;
            }

            $filtered[] = [
                'id' => (string) ($entity['id'] ?? ''),
                'name' => (string) ($entity['name'] ?? ''),
                'type' => $resourceType,
                'environment' => (string) ($normalizedTags['environment'] ?? ''),
                'owner' => (string) ($normalizedTags['owner'] ?? ''),
                'site' => (string) ($normalizedTags['site'] ?? ''),
                'zone' => (string) ($normalizedTags['zone'] ?? ''),
                'lifecycle' => (string) ($normalizedTags['lifecycle'] ?? ''),
                'status' => $this->resourceStatusLabel($entity),
                'updated' => '—',
                'sourcePath' => (string) ($entity['sourcePath'] ?? ''),
            ];
        }

        ksort($types);

        $html = $this->renderer->render('entities/list', [
            'title' => 'リソース',
            'currentPath' => '/resources',
            'entities' => $filtered,
            'filters' => [
                'q' => $query,
                'type' => $type,
                'environment' => $environmentFilter,
                'owner' => $ownerFilter,
                'site' => $siteFilter,
                'zone' => $zoneFilter,
                'lifecycle' => $lifecycleFilter,
            ],
            'types' => array_keys($types),
            'tagFilterOptions' => [
                'environment' => is_array($tagKeys['environment']['values'] ?? null) ? $tagKeys['environment']['values'] : [],
                'owner' => is_array($tagKeys['owner']['values'] ?? null) ? $tagKeys['owner']['values'] : [],
                'site' => is_array($tagKeys['site']['values'] ?? null) ? $tagKeys['site']['values'] : [],
                'zone' => is_array($tagKeys['zone']['values'] ?? null) ? $tagKeys['zone']['values'] : [],
                'lifecycle' => is_array($tagKeys['lifecycle']['values'] ?? null) ? $tagKeys['lifecycle']['values'] : [],
            ],
        ]);

        return Response::html($html);
    }

    public function changeList(Request $request): Response
    {
        $html = $this->renderer->render('changes/list', [
            'title' => '変更',
            'currentPath' => '/changes',
            'changes' => $this->changeService->listRecentChanges(50),
        ]);

        return Response::html($html);
    }

    public function relationList(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));

        $relations = $this->relationRepository->listRelations();
        $types = [];
        $items = [];

        foreach ($relations as $relation) {
            $relationType = (string) ($relation['type'] ?? '');
            if ($relationType !== '') {
                $types[$relationType] = true;
            }
            if ($type !== '' && $relationType !== $type) {
                continue;
            }

            $haystack = strtolower(json_encode($relation, JSON_UNESCAPED_UNICODE) ?: '');
            if ($query !== '' && !str_contains($haystack, strtolower($query))) {
                continue;
            }

            $items[] = [
                'id' => (string) ($relation['id'] ?? ''),
                'type' => $relationType,
                'from' => (string) ($relation['from'] ?? ''),
                'to' => (string) ($relation['to'] ?? ''),
                'status' => ((string) ($relation['from'] ?? '') !== '' && (string) ($relation['to'] ?? '') !== '') ? 'Valid' : 'Warning',
                'sourcePath' => (string) ($relation['sourcePath'] ?? ''),
            ];
        }

        ksort($types);

        $html = $this->renderer->render('relations/list', [
            'title' => '依存関係',
            'currentPath' => '/dependencies',
            'relations' => $items,
            'filters' => ['q' => $query, 'type' => $type],
            'relationTypes' => array_keys($types),
        ]);

        return Response::html($html);
    }

    public function domainPackList(Request $request): Response
    {
        $packs = $this->domainPackRepository->listDomainPacks();
        $impactByPack = [];
        foreach ($packs as $pack) {
            if (($pack['installed'] ?? false) !== true) {
                continue;
            }
            $packId = (string) ($pack['id'] ?? '');
            if ($packId === '') {
                continue;
            }
            try {
                $impactByPack[$packId] = $this->domainPackRepository->packImpact($packId);
            } catch (\Throwable) {
                continue;
            }
        }

        $html = $this->renderer->render('domain-packs/list', [
            'title' => 'タイプパック',
            'currentPath' => '/type-packs',
            'packs' => $packs,
            'impactByPack' => $impactByPack,
        ]);

        return Response::html($html);
    }

    public function installTypePack(Request $request): Response
    {
        if (!$this->validateCsrf($request)) {
            return Response::html('CSRF トークンが一致しません。', 419);
        }

        $name = trim((string) $request->post('name', ''));
        if ($name === '') {
            return Response::redirect('/type-packs');
        }

        $this->domainPackRepository->installPack($name);

        return Response::redirect('/type-packs');
    }

    public function enableTypePack(Request $request, array $params): Response
    {
        if (!$this->validateCsrf($request)) {
            return Response::html('CSRF トークンが一致しません。', 419);
        }

        $name = trim((string) ($params['name'] ?? ''));
        if ($name === '') {
            return Response::redirect('/type-packs');
        }

        $this->domainPackRepository->enablePack($name);

        return Response::redirect('/type-packs');
    }

    public function disableTypePack(Request $request, array $params): Response
    {
        if (!$this->validateCsrf($request)) {
            return Response::html('CSRF トークンが一致しません。', 419);
        }

        $name = trim((string) ($params['name'] ?? ''));
        if ($name === '') {
            return Response::redirect('/type-packs');
        }

        $this->domainPackRepository->disablePack($name);

        return Response::redirect('/type-packs');
    }

    public function uninstallTypePack(Request $request, array $params): Response
    {
        if (!$this->validateCsrf($request)) {
            return Response::html('CSRF トークンが一致しません。', 419);
        }

        $name = trim((string) ($params['name'] ?? ''));
        if ($name === '') {
            return Response::redirect('/type-packs');
        }

        $this->domainPackRepository->uninstallPack($name);

        return Response::redirect('/type-packs');
    }

    public function newRelationForm(Request $request): Response
    {
        $entities = $this->entityRepository->listEntities();
        $selectedSource = trim((string) $request->query('source', ''));
        $selectedType = trim((string) $request->query('type', ''));
        $selectedTarget = trim((string) $request->query('target', ''));

        $relationTypes = $this->listRelationTypesForSource($selectedSource, $entities);
        if ($selectedType !== '' && !in_array($selectedType, $relationTypes, true)) {
            $selectedType = '';
        }

        $targetCandidates = $this->filterTargetCandidatesForRelation($selectedSource, $selectedType, $entities);
        if ($selectedTarget !== '' && !in_array($selectedTarget, array_map(static fn (array $e): string => (string) $e['id'], $targetCandidates), true)) {
            $selectedTarget = '';
        }

        $html = $this->renderer->render('relations/form', [
            'title' => '高度な依存関係を作成',
            'currentPath' => '/dependencies',
            'mode' => 'create',
            'relation' => null,
            'error' => null,
            'entities' => $entities,
            'targetEntities' => $targetCandidates,
            'relationTypes' => $relationTypes,
            'selectedSource' => $selectedSource,
            'selectedRelationType' => $selectedType,
            'selectedTarget' => $selectedTarget,
            'relationSchemas' => $this->relationSchemaMap(),
            'sourceEntity' => $this->findEntityById($selectedSource, $entities),
            'targetEntity' => $this->findEntityById($selectedTarget, $entities),
        ]);

        return Response::html($html);
    }

    /**
     * @param array<string,string> $params
     */
    public function editRelationForm(Request $request, array $params): Response
    {
        $id = $params['id'] ?? '';
        $relation = $this->relationRepository->getRelation($id);
        if ($relation === null) {
            return Response::html('依存関係が見つかりません。', 404);
        }

        $entities = $this->entityRepository->listEntities();
        $record = is_array($relation['record'] ?? null) ? $relation['record'] : [];
        $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
        $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
        $selectedSource = (string) ($spec['from'] ?? '');
        $selectedType = (string) ($metadata['type'] ?? '');
        $selectedTarget = (string) ($spec['to'] ?? '');

        $html = $this->renderer->render('relations/form', [
            'title' => '高度な依存関係を編集: ' . $id,
            'currentPath' => '/dependencies',
            'mode' => 'edit',
            'relation' => $relation,
            'error' => null,
            'entities' => $entities,
            'targetEntities' => $this->filterTargetCandidatesForRelation($selectedSource, $selectedType, $entities),
            'relationTypes' => $this->listRelationTypesForSource($selectedSource, $entities),
            'selectedSource' => $selectedSource,
            'selectedRelationType' => $selectedType,
            'selectedTarget' => $selectedTarget,
            'relationSchemas' => $this->relationSchemaMap(),
            'sourceEntity' => $this->findEntityById($selectedSource, $entities),
            'targetEntity' => $this->findEntityById($selectedTarget, $entities),
        ]);

        return Response::html($html);
    }

    /**
     * @param array<string,string> $params
     */
    public function upsertRelation(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf($request)) {
            return Response::html('CSRF トークンが一致しません。', 419);
        }

        try {
            $record = $this->buildRelationRecord($request);
            $sourcePath = trim((string) $request->post('sourcePath', ''));
            $actor = trim((string) $request->post('actor', 'human-ui'));
            $actorType = trim((string) $request->post('actorType', 'human'));

            $change = $this->changeService->createChange($actor !== '' ? $actor : 'human-ui', $actorType);
            $operation = ['type' => 'upsert_relation', 'relation' => $record];
            if ($sourcePath !== '') {
                $operation['sourcePath'] = $sourcePath;
            }

            $this->changeService->addOperations((string) $change['id'], $operation);
            $this->changeService->validateChange((string) $change['id']);

            return Response::redirect('/changes/' . rawurlencode((string) $change['id']));
        } catch (\Throwable $exception) {
            $existingId = $params['id'] ?? null;
            $relation = $existingId !== null ? $this->relationRepository->getRelation($existingId) : null;
            $entities = $this->entityRepository->listEntities();
            $selectedSource = trim((string) $request->post('from', ''));
            $selectedType = trim((string) $request->post('type', ''));
            $selectedTarget = trim((string) $request->post('to', ''));
            $html = $this->renderer->render('relations/form', [
                'title' => '依存関係フォーム',
                'currentPath' => '/dependencies',
                'mode' => $existingId !== null ? 'edit' : 'create',
                'relation' => $relation,
                'error' => $exception->getMessage(),
                'entities' => $entities,
                'targetEntities' => $this->filterTargetCandidatesForRelation($selectedSource, $selectedType, $entities),
                'relationTypes' => $this->listRelationTypesForSource($selectedSource, $entities),
                'selectedSource' => $selectedSource,
                'selectedRelationType' => $selectedType,
                'selectedTarget' => $selectedTarget,
                'relationSchemas' => $this->relationSchemaMap(),
                'sourceEntity' => $this->findEntityById($selectedSource, $entities),
                'targetEntity' => $this->findEntityById($selectedTarget, $entities),
            ]);

            return Response::html($html, 422);
        }
    }

    /**
     * @param array<string,string> $params
     */
    public function entityDetail(Request $request, array $params): Response
    {
        $id = $params['id'] ?? '';
        $entity = $this->entityRepository->getEntity($id);
        if ($entity === null) {
            return Response::html('リソースが見つかりません。', 404);
        }

        $allDependencies = $this->relationRepository->listRelations();
        $dependsOn = [];
        $usedBy = [];
        foreach ($allDependencies as $dependency) {
            $from = (string) ($dependency['from'] ?? '');
            $to = (string) ($dependency['to'] ?? '');
            if ($from === $id) {
                $dependsOn[] = $dependency;
            }
            if ($to === $id) {
                $usedBy[] = $dependency;
            }
        }

        $record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
        $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
        $resourceType = (string) ($metadata['type'] ?? '');
        $schemasById = $this->activeEntitySchemasById();
        $schema = $schemasById[$resourceType] ?? null;
        $dependencySlots = is_array($schema['dependencySlots'] ?? null) ? $schema['dependencySlots'] : [];
        $slotGroups = $this->groupDependenciesBySlots($id, $dependsOn, $usedBy, $dependencySlots);
        $normalizedTags = $this->normalizedTagsForRecord($record);
        $tagGroups = $this->groupTagsForDetail($normalizedTags);

        $html = $this->renderer->render('entities/detail', [
            'title' => 'リソース: ' . $id,
            'currentPath' => '/resources',
            'entity' => $entity,
            'dependsOn' => $dependsOn,
            'usedBy' => $usedBy,
            'tagGroups' => $tagGroups,
            'dependencySlotGroups' => $slotGroups,
        ]);

        return Response::html($html);
    }

    public function newEntityForm(Request $request): Response
    {
        $selectedType = (string) $request->query('schema', '');
        $settings = $this->settingsRepository->loadSettings();
        $entities = $this->entityRepository->listEntities();

        $html = $this->renderer->render('entities/form', [
            'title' => 'リソースを作成',
            'currentPath' => '/resources',
            'mode' => 'create',
            'entity' => null,
            'error' => null,
            'schemas' => $this->activeSchemas(),
            'selectedSchemaId' => $selectedType,
            'entities' => $entities,
            'relationTypes' => $this->listRelationTypes(),
            'fieldErrors' => [],
            'yamlPreview' => null,
            'settings' => $settings,
            'existingRelations' => $this->relationRepository->listRelations(),
        ]);

        return Response::html($html);
    }

    /**
     * @param array<string,string> $params
     */
    public function editEntityForm(Request $request, array $params): Response
    {
        $id = $params['id'] ?? '';
        $entity = $this->entityRepository->getEntity($id);
        if ($entity === null) {
            return Response::html('リソースが見つかりません。', 404);
        }
        $settings = $this->settingsRepository->loadSettings();
        $entities = $this->entityRepository->listEntities();

        $html = $this->renderer->render('entities/form', [
            'title' => 'リソースを編集: ' . $id,
            'currentPath' => '/resources',
            'mode' => 'edit',
            'entity' => $entity,
            'error' => null,
            'schemas' => $this->activeSchemas(),
            'selectedSchemaId' => (string) $request->query('schema', ''),
            'entities' => $entities,
            'relationTypes' => $this->listRelationTypes(),
            'fieldErrors' => [],
            'yamlPreview' => null,
            'settings' => $settings,
            'existingRelations' => $this->relationRepository->listRelations(),
        ]);

        return Response::html($html);
    }

    /**
     * @param array<string,string> $params
     */
    public function upsertEntity(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf($request)) {
            return Response::html('CSRF トークンが一致しません。', 419);
        }

        try {
            $record = $this->buildEntityRecord($request);
            $sourcePath = trim((string) $request->post('sourcePath', ''));

            $actor = trim((string) $request->post('actor', 'human-ui'));
            $actorType = trim((string) $request->post('actorType', 'human'));

            $change = $this->changeService->createChange($actor !== '' ? $actor : 'human-ui', $actorType);
            $operation = [
                'type' => 'upsert_entity',
                'entity' => $record,
            ];
            if ($sourcePath !== '') {
                $operation['sourcePath'] = $sourcePath;
            }

            $this->changeService->addOperations((string) $change['id'], $operation);
            foreach ($this->buildDependencyOperationsFromRequest($record, $request) as $dependencyOperation) {
                $this->changeService->addOperations((string) $change['id'], $dependencyOperation);
            }
            $this->changeService->validateChange((string) $change['id']);

            return Response::redirect('/changes/' . rawurlencode((string) $change['id']));
        } catch (\Throwable $exception) {
            $existingId = $params['id'] ?? null;
            $entity = $existingId !== null ? $this->entityRepository->getEntity($existingId) : null;
            $entities = $this->entityRepository->listEntities();
            $html = $this->renderer->render('entities/form', [
                'title' => 'リソースフォーム',
                'currentPath' => '/resources',
                'mode' => $existingId !== null ? 'edit' : 'create',
                'entity' => $entity,
                'error' => $exception->getMessage(),
                'schemas' => $this->activeSchemas(),
                'selectedSchemaId' => (string) $request->post('type', ''),
                'entities' => $entities,
                'relationTypes' => $this->listRelationTypes(),
                'fieldErrors' => [],
                'yamlPreview' => null,
                'settings' => $this->settingsRepository->loadSettings(),
                'existingRelations' => $this->relationRepository->listRelations(),
            ]);

            return Response::html($html, 422);
        }
    }

    /**
     * @param array<string,string> $params
     */
    public function changeDetail(Request $request, array $params): Response
    {
        $id = $params['id'] ?? '';
        $change = $this->changeService->getChange($id);

        if ($change === null) {
            return Response::html('ドラフト変更が見つかりません。', 404);
        }

        $diff = $this->changeService->diffChange($id);

        $html = $this->renderer->render('changes/detail', [
            'title' => '変更を確認',
            'currentPath' => '/changes',
            'change' => $change,
            'diff' => $diff,
        ]);

        return Response::html($html);
    }

    /**
     * @param array<string,string> $params
     */
    public function validateChange(Request $request, array $params): Response
    {
        if (!$this->validateCsrf($request)) {
            return Response::html('CSRF トークンが一致しません。', 419);
        }

        $id = $params['id'] ?? '';

        try {
            $this->changeService->validateChange($id);

            return Response::redirect('/changes/' . rawurlencode($id));
        } catch (\Throwable $exception) {
            return Response::html('検証エラー: ' . $exception->getMessage(), 422);
        }
    }

    /**
     * @param array<string,string> $params
     */
    public function commitChange(Request $request, array $params): Response
    {
        if (!$this->validateCsrf($request)) {
            return Response::html('CSRF トークンが一致しません。', 419);
        }

        $id = $params['id'] ?? '';

        try {
            $message = (string) $request->post('commitMessage', '');
            $createGitCommit = $request->post('createGitCommit', '0') === '1';
            $this->changeService->commitChange($id, $message, $createGitCommit);

            return Response::redirect('/changes/' . rawurlencode($id));
        } catch (\Throwable $exception) {
            return Response::html('保存エラー: ' . $exception->getMessage(), 422);
        }
    }

    /**
     * @param array<string,string> $params
     */
    public function abortChange(Request $request, array $params): Response
    {
        if (!$this->validateCsrf($request)) {
            return Response::html('CSRF トークンが一致しません。', 419);
        }

        $id = $params['id'] ?? '';

        try {
            $this->changeService->abortChange($id);

            return Response::redirect('/changes/' . rawurlencode($id));
        } catch (\Throwable $exception) {
            return Response::html('破棄エラー: ' . $exception->getMessage(), 422);
        }
    }

    public function validationPage(Request $request): Response
    {
        $result = $this->changeService->validateCurrentRegistry();
        $html = $this->renderer->render('validation/index', [
            'title' => '検証',
            'currentPath' => '/validation',
            'result' => $result,
        ]);

        return Response::html($html);
    }

    public function gitDiffPage(Request $request): Response
    {
        $diff = $this->gitService->diffRegistryAndCataloga();

        $html = $this->renderer->render('git/diff', [
            'title' => '技術差分',
            'currentPath' => '/git/diff',
            'diff' => $diff,
        ]);

        return Response::html($html);
    }

    /** @return array<int,string> */
    private function listRelationTypes(): array
    {
        $types = [];
        foreach ($this->activeSchemas() as $schema) {
            if (($schema['kind'] ?? 'entity') !== 'relation') {
                continue;
            }
            $types[] = (string) ($schema['id'] ?? '');
        }
        $types = array_values(array_unique(array_filter($types)));
        sort($types);

        return $types;
    }

    private function validateCsrf(Request $request): bool
    {
        $token = (string) $request->post('csrf_token', '');
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        return is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
    }

    /**
     * @return array<string,mixed>
     */
    private function buildEntityRecord(Request $request): array
    {
        $advanced = $request->post('advancedMode', '0') === '1';
        $id = trim((string) $request->post('id', ''));
        $type = trim((string) $request->post('type', ''));
        $name = trim((string) $request->post('name', ''));
        if ($type === '') {
            throw new \RuntimeException('Resource type is required.');
        }
        if ($name === '') {
            throw new \RuntimeException('Name is required.');
        }
        if ($id === '') {
            $typePart = $this->slugify($type);
            $namePart = $this->slugify($name);
            $id = $typePart . '.' . $namePart;
        }

        $labels = $this->decodeJsonObject(trim((string) $request->post('labels', '{}')), 'labels');
        $spec = $advanced ? $this->decodeJsonObject(trim((string) $request->post('spec', '{}')), 'spec') : $this->buildSpecFromSchema($request);
        $settings = $this->settingsRepository->loadSettings();
        $reservedPrefixes = is_array($settings['reserved_prefixes'] ?? null) ? $settings['reserved_prefixes'] : ['cataloga:'];
        $tags = $this->buildTagsFromRequest($request, $spec, $reservedPrefixes);

        return [
            'apiVersion' => 'cataloga.io/v2',
            'kind' => 'Entity',
            'metadata' => ['id' => $id, 'type' => $type, 'name' => $name, 'labels' => $labels, 'tags' => $tags],
            'spec' => $spec,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildRelationRecord(Request $request): array
    {
        return [
            'apiVersion' => 'cataloga.io/v2',
            'kind' => 'Relation',
            'metadata' => [
                'id' => trim((string) $request->post('id', '')),
                'type' => trim((string) $request->post('type', '')),
                'name' => trim((string) $request->post('name', '')),
            ],
            'spec' => [
                'from' => trim((string) $request->post('from', '')),
                'to' => trim((string) $request->post('to', '')),
                'attributes' => $this->decodeJsonObject(trim((string) $request->post('attributes', '{}')), 'attributes'),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function buildSpecFromSchema(Request $request): array
    {
        $raw = $request->post('schema_fields', []);
        if (!is_array($raw)) {
            return [];
        }

        $spec = [];
        foreach ($raw as $k => $v) {
            $key = (string) $k;
            if (in_array($key, ['environment', 'owner', 'site', 'zone', 'visibility', 'lifecycle', 'criticality', 'managed-by', 'cost-center', 'data-classification', 'backup-policy', 'patch-policy'], true)) {
                continue;
            }
            if (is_array($v)) {
                $spec[$key] = array_values(array_filter(array_map('trim', $v), static fn (string $x): bool => $x !== ''));
                continue;
            }

            $val = trim((string) $v);
            if ($val === '') {
                continue;
            }

            if ($val === 'true' || $val === 'false') {
                $spec[$key] = $val === 'true';
                continue;
            }

            if (($val[0] ?? '') === '{' || ($val[0] ?? '') === '[') {
                $decoded = json_decode($val, true);
                $spec[$key] = is_array($decoded) ? $decoded : $val;
                continue;
            }

            $spec[$key] = $val;
        }

        return $spec;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value) ?? '';
        $value = preg_replace('/-+/', '-', $value) ?? '';
        $value = preg_replace('/\.+/', '.', $value) ?? '';

        return trim($value, '-');
    }

    /**
     * @param array<string,mixed> $entityRecord
     * @return array<int,array<string,mixed>>
     */
    private function buildDependencyOperationsFromRequest(array $entityRecord, Request $request): array
    {
        $resourceId = (string) ($entityRecord['metadata']['id'] ?? '');
        if ($resourceId === '') {
            return [];
        }

        $resourceType = (string) ($entityRecord['metadata']['type'] ?? '');

        $operations = [];
        $schema = $this->activeEntitySchemasById()[$resourceType] ?? null;
        $slots = is_array($schema['dependencySlots'] ?? null) ? $schema['dependencySlots'] : [];
        if ($slots !== []) {
            foreach ($slots as $slot) {
                $slotKey = (string) ($slot['key'] ?? '');
                $relationType = (string) ($slot['relation_type'] ?? '');
                $direction = (string) ($slot['direction'] ?? 'outgoing');
                if ($slotKey === '' || $relationType === '') {
                    continue;
                }

                $raw = $request->post('dependency_slot_target_' . $slotKey, []);
                $targets = is_array($raw) ? $raw : [$raw];
                foreach ($targets as $targetRaw) {
                    $target = trim((string) $targetRaw);
                    if ($target === '') {
                        continue;
                    }

                    $from = $direction === 'incoming' ? $target : $resourceId;
                    $to = $direction === 'incoming' ? $resourceId : $target;
                    $relationId = $this->slugify($from . '-' . $relationType . '-' . $to);
                    $operations[] = [
                        'type' => 'upsert_relation',
                        'relation' => [
                            'apiVersion' => 'cataloga.io/v2',
                            'kind' => 'Relation',
                            'metadata' => [
                                'id' => $relationId,
                                'type' => $relationType,
                                'name' => $from . ' ' . $relationType . ' ' . $to,
                            ],
                            'spec' => [
                                'from' => $from,
                                'to' => $to,
                                'attributes' => ['slot' => $slotKey],
                            ],
                        ],
                    ];
                }
            }
        }

        $types = $request->post('dependency_type', []);
        $targets = $request->post('dependency_target', []);
        if (is_array($types) && is_array($targets)) {
            $count = min(count($types), count($targets));
            for ($i = 0; $i < $count; $i++) {
                $type = trim((string) ($types[$i] ?? ''));
                $target = trim((string) ($targets[$i] ?? ''));
                if ($type === '' || $target === '') {
                    continue;
                }

                $relationId = $this->slugify($resourceId . '-' . $type . '-' . $target);
                $operations[] = [
                    'type' => 'upsert_relation',
                    'relation' => [
                        'apiVersion' => 'cataloga.io/v2',
                        'kind' => 'Relation',
                        'metadata' => [
                            'id' => $relationId,
                            'type' => $type,
                            'name' => $resourceId . ' ' . $type . ' ' . $target,
                        ],
                        'spec' => [
                            'from' => $resourceId,
                            'to' => $target,
                            'attributes' => [],
                        ],
                    ],
                ];
            }
        }

        return $operations;
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJsonObject(string $raw, string $field): array
    {
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException($field . ' must be valid JSON object.');
        }

        return $decoded;
    }

    /**
     * @param array<string,mixed> $entity
     */
    private function resourceStatusLabel(array $entity): string
    {
        $record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
        $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
        $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];

        if ((string) ($metadata['id'] ?? '') === '' || (string) ($metadata['type'] ?? '') === '' || (string) ($metadata['name'] ?? '') === '') {
            return 'Error';
        }

        if (($spec['status'] ?? null) === 'warning') {
            return 'Warning';
        }

        return 'Valid';
    }

    /**
     * @param array<string,mixed> $record
     * @return array<string,string>
     */
    private function normalizedTagsForRecord(array $record): array
    {
        $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
        $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
        $rawTags = $metadata['tags'] ?? [];

        $tags = [];
        if (is_array($rawTags)) {
            foreach ($rawTags as $key => $value) {
                if (is_int($key)) {
                    $legacy = trim((string) $value);
                    if ($legacy === '') {
                        continue;
                    }
                    if (str_contains($legacy, ':')) {
                        [$k, $v] = explode(':', $legacy, 2);
                        $legacyKey = trim($k);
                        if ($legacyKey === '') {
                            continue;
                        }
                        $tags[$legacyKey] = trim($v);
                        continue;
                    }
                    $tags[$legacy] = '';
                    continue;
                }

                $tagKey = trim((string) $key);
                if ($tagKey === '') {
                    continue;
                }
                $tags[$tagKey] = is_scalar($value) ? trim((string) $value) : '';
            }
        }

        foreach (['environment', 'owner', 'site', 'zone', 'visibility', 'lifecycle', 'criticality', 'managed-by', 'cost-center', 'data-classification', 'backup-policy', 'patch-policy'] as $key) {
            if (($tags[$key] ?? '') !== '') {
                continue;
            }
            $legacyValue = trim((string) ($spec[$key] ?? ''));
            if ($legacyValue !== '') {
                $tags[$key] = $legacyValue;
            }
        }

        return $tags;
    }

    /**
     * @param array<string,string> $tags
     * @return array<string,array<string,string>>
     */
    private function groupTagsForDetail(array $tags): array
    {
        $basicKeys = ['environment', 'owner', 'site', 'zone', 'lifecycle'];
        $noteKeys = ['note'];
        $todoKeys = ['todo'];
        $riskKeys = ['risk'];

        $basic = [];
        $notes = [];
        $todos = [];
        $risks = [];
        $others = [];
        foreach ($tags as $key => $value) {
            if (in_array($key, $basicKeys, true)) {
                $basic[$key] = $value;
                continue;
            }
            if (in_array($key, $noteKeys, true)) {
                $notes[$key] = $value;
                continue;
            }
            if (in_array($key, $todoKeys, true)) {
                $todos[$key] = $value;
                continue;
            }
            if (in_array($key, $riskKeys, true)) {
                $risks[$key] = $value;
                continue;
            }
            $others[$key] = $value;
        }

        return [
            'basic' => $basic,
            'note' => $notes,
            'todo' => $todos,
            'risk' => $risks,
            'other' => $others,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildTagsFromRequest(Request $request, array &$spec, array $reservedPrefixes): array
    {
        $tags = [];

        $basicTagKeys = is_array($request->post('basic_tag_key', null)) ? $request->post('basic_tag_key', []) : [];
        $basicTagValues = is_array($request->post('basic_tag_value', null)) ? $request->post('basic_tag_value', []) : [];
        $count = min(count($basicTagKeys), count($basicTagValues));
        for ($i = 0; $i < $count; $i++) {
            $key = trim((string) ($basicTagKeys[$i] ?? ''));
            if ($key === '') {
                continue;
            }
            if ($this->isReservedTagKey($key, $reservedPrefixes)) {
                continue;
            }
            $tags[$key] = trim((string) ($basicTagValues[$i] ?? ''));
        }

        $additionalKeys = is_array($request->post('tag_key', null)) ? $request->post('tag_key', []) : [];
        $additionalValues = is_array($request->post('tag_value', null)) ? $request->post('tag_value', []) : [];
        $additionalCount = min(count($additionalKeys), count($additionalValues));
        for ($i = 0; $i < $additionalCount; $i++) {
            $key = trim((string) ($additionalKeys[$i] ?? ''));
            if ($key === '') {
                continue;
            }
            if ($this->isReservedTagKey($key, $reservedPrefixes)) {
                continue;
            }
            $tags[$key] = trim((string) ($additionalValues[$i] ?? ''));
        }

        if ($tags === []) {
            $legacyTagsRaw = trim((string) $request->post('tags', ''));
            if ($legacyTagsRaw !== '') {
                foreach (array_filter(array_map('trim', explode(',', $legacyTagsRaw)), static fn (string $v): bool => $v !== '') as $legacy) {
                    if (str_contains($legacy, ':')) {
                        [$legacyKey, $legacyValue] = explode(':', $legacy, 2);
                        $key = trim($legacyKey);
                        if ($key === '' || $this->isReservedTagKey($key, $reservedPrefixes)) {
                            continue;
                        }
                        $tags[$key] = trim($legacyValue);
                        continue;
                    }
                    if ($this->isReservedTagKey($legacy, $reservedPrefixes)) {
                        continue;
                    }
                    $tags[$legacy] = '';
                }
            }
        }

        foreach (['environment', 'owner', 'site', 'zone', 'visibility', 'lifecycle', 'criticality', 'managed-by', 'cost-center', 'data-classification', 'backup-policy', 'patch-policy'] as $legacyKey) {
            $legacyValue = trim((string) ($spec[$legacyKey] ?? ''));
            if ($legacyValue !== '' && !isset($tags[$legacyKey])) {
                $tags[$legacyKey] = $legacyValue;
            }
            unset($spec[$legacyKey]);
        }

        ksort($tags);

        return $tags;
    }

    private function isReservedTagKey(string $key, array $reservedPrefixes): bool
    {
        foreach ($reservedPrefixes as $prefix) {
            if (!is_string($prefix) || $prefix === '') {
                continue;
            }
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function activeEntitySchemasById(): array
    {
        $items = [];
        foreach ($this->activeSchemas() as $schema) {
            if (($schema['kind'] ?? 'entity') !== 'entity') {
                continue;
            }
            $id = (string) ($schema['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $items[$id] = $schema;
        }

        return $items;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function relationSchemaMap(): array
    {
        $items = [];
        foreach ($this->activeSchemas() as $schema) {
            if (($schema['kind'] ?? 'entity') !== 'relation') {
                continue;
            }
            $id = (string) ($schema['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $items[$id] = $schema;
        }

        return $items;
    }

    private function findEntityById(string $id, array $entities): ?array
    {
        if ($id === '') {
            return null;
        }
        foreach ($entities as $entity) {
            if ((string) ($entity['id'] ?? '') === $id) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $entities
     * @return array<int,string>
     */
    private function listRelationTypesForSource(string $sourceId, array $entities): array
    {
        $all = $this->listRelationTypes();
        if ($sourceId === '') {
            return $all;
        }

        $source = $this->findEntityById($sourceId, $entities);
        if ($source === null) {
            return $all;
        }

        $sourceType = (string) ($source['type'] ?? '');
        if ($sourceType === '') {
            return $all;
        }

        $allowed = [];
        foreach ($this->relationSchemaMap() as $relationType => $schema) {
            $sourceTypes = is_array($schema['sourceTypes'] ?? null) ? $schema['sourceTypes'] : [];
            if ($sourceTypes !== [] && !in_array($sourceType, $sourceTypes, true)) {
                continue;
            }
            $allowed[] = $relationType;
        }

        if ($allowed === []) {
            return $all;
        }
        sort($allowed);

        return $allowed;
    }

    /**
     * @param array<int,array<string,mixed>> $entities
     * @return array<int,array<string,mixed>>
     */
    private function filterTargetCandidatesForRelation(string $sourceId, string $relationType, array $entities): array
    {
        if ($relationType === '') {
            return $entities;
        }

        $schema = $this->relationSchemaMap()[$relationType] ?? null;
        if ($schema === null) {
            return $entities;
        }

        if ($sourceId !== '') {
            $source = $this->findEntityById($sourceId, $entities);
            if ($source === null) {
                return $entities;
            }

            $sourceType = (string) ($source['type'] ?? '');
            $sourceTypes = is_array($schema['sourceTypes'] ?? null) ? $schema['sourceTypes'] : [];
            if ($sourceTypes !== [] && !in_array($sourceType, $sourceTypes, true)) {
                return [];
            }
        }

        $targetTypes = is_array($schema['targetTypes'] ?? null) ? $schema['targetTypes'] : [];
        if ($targetTypes === []) {
            return $entities;
        }

        $filtered = [];
        foreach ($entities as $candidate) {
            $candidateType = (string) ($candidate['type'] ?? '');
            if (in_array($candidateType, $targetTypes, true)) {
                $filtered[] = $candidate;
            }
        }

        return $filtered;
    }

    /**
     * @param array<int,array<string,mixed>> $dependsOn
     * @param array<int,array<string,mixed>> $usedBy
     * @param array<int,array<string,mixed>> $slots
     * @return array<string,mixed>
     */
    private function groupDependenciesBySlots(string $resourceId, array $dependsOn, array $usedBy, array $slots): array
    {
        if ($slots === []) {
            return ['slots' => [], 'other' => array_merge($dependsOn, $usedBy)];
        }

        $grouped = [];
        $assigned = [];
        foreach ($slots as $slot) {
            $slotKey = (string) ($slot['key'] ?? '');
            $relationType = (string) ($slot['relation_type'] ?? '');
            $direction = (string) ($slot['direction'] ?? 'outgoing');
            if ($slotKey === '' || $relationType === '') {
                continue;
            }

            $items = [];
            $pool = $direction === 'incoming' ? $usedBy : $dependsOn;
            foreach ($pool as $relation) {
                if ((string) ($relation['type'] ?? '') !== $relationType) {
                    continue;
                }
                $relationId = (string) ($relation['id'] ?? '');
                if ($relationId !== '') {
                    $assigned[$relationId] = true;
                }
                $peerId = $direction === 'incoming' ? (string) ($relation['from'] ?? '') : (string) ($relation['to'] ?? '');
                $items[] = [
                    'relation' => $relation,
                    'peer_id' => $peerId,
                ];
            }

            $grouped[] = [
                'key' => $slotKey,
                'label' => (string) ($slot['label'] ?? $slotKey),
                'description' => (string) ($slot['description'] ?? ''),
                'direction' => $direction,
                'multiple' => (bool) ($slot['multiple'] ?? true),
                'required' => (bool) ($slot['required'] ?? false),
                'items' => $items,
            ];
        }

        $others = [];
        foreach (array_merge($dependsOn, $usedBy) as $relation) {
            $relationId = (string) ($relation['id'] ?? '');
            if ($relationId !== '' && isset($assigned[$relationId])) {
                continue;
            }
            $others[] = $relation;
        }

        return ['slots' => $grouped, 'other' => $others];
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
