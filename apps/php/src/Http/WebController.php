<?php

declare(strict_types=1);

namespace Cataloga\Http;

use Cataloga\Mutation\ChangeService;
use Cataloga\Registry\DomainPackRepository;
use Cataloga\Registry\EntityRepository;
use Cataloga\Registry\RegistrySettingsRepository;
use Cataloga\Registry\RelationRepository;
use Cataloga\Registry\SchemaRepository;
use Cataloga\View\ResourceFormViewModel;
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
            $status = (string) ($change['status'] ?? 'draft');
            if (!in_array($status, ['saved', 'failed', 'discarded'], true)) {
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
        $resourceProfiles = $this->resourceTypeProfilesByType($settings, $this->activeEntitySchemasById());

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

            $row = [
                'id' => (string) ($entity['id'] ?? ''),
                'name' => (string) ($entity['name'] ?? ''),
                'type' => $resourceType,
                'record' => $record,
                'environment' => (string) ($normalizedTags['environment'] ?? ''),
                'owner' => (string) ($normalizedTags['owner'] ?? ''),
                'site' => (string) ($normalizedTags['site'] ?? ''),
                'zone' => (string) ($normalizedTags['zone'] ?? ''),
                'lifecycle' => (string) ($normalizedTags['lifecycle'] ?? ''),
                'status' => $this->resourceStatusLabel($entity),
                'updated' => '—',
                'sourcePath' => (string) ($entity['sourcePath'] ?? ''),
            ];
            $row['computed'] = $this->buildTypeSpecificComputedColumns($row);
            $filtered[] = $row;
        }

        ksort($types);

        $defaultColumns = [
            ['label' => '名前', 'path' => 'metadata.name'],
            ['label' => 'タイプ', 'path' => 'metadata.type'],
            ['label' => '環境', 'path' => 'metadata.tags.environment'],
            ['label' => 'オーナー', 'path' => 'metadata.tags.owner'],
            ['label' => 'サイト', 'path' => 'metadata.tags.site'],
            ['label' => '状態', 'path' => 'computed.status'],
        ];
        $activeProfile = $type !== '' ? ($resourceProfiles[$type] ?? null) : null;
        $listColumns = is_array($activeProfile['list_columns'] ?? null) && ($activeProfile['list_columns'] ?? []) !== []
            ? array_values($activeProfile['list_columns'])
            : $defaultColumns;

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
            'listColumns' => $listColumns,
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
                'derived' => (bool) ($relation['derived'] ?? false),
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
        $specJson = format_json($spec);
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
    public function editDependencySlotForm(Request $request, array $params): Response
    {
        $id = (string) ($params['id'] ?? '');
        $slotKey = (string) ($params['slot'] ?? '');
        $entity = $this->entityRepository->getEntity($id);
        if ($entity === null) {
            return Response::html('リソースが見つかりません。', 404);
        }

        $slot = $this->dependencySlotForEntity($entity, $slotKey);
        if ($slot === null) {
            return Response::html('依存関係スロットが見つかりません。', 404);
        }
        if ((string) ($slot['direction'] ?? 'outgoing') !== 'outgoing') {
            return Response::html('このスロットはまだ詳細画面から編集できません。高度な依存関係を使用してください。', 422);
        }

        $record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
        $dependencies = is_array($record['dependencies'] ?? null) ? $record['dependencies'] : [];
        $selectedTargets = is_array($dependencies[$slotKey] ?? null) ? array_values(array_map('strval', $dependencies[$slotKey])) : [];

        $html = $this->renderer->render('entities/dependency-slot-form', [
            'title' => '依存関係を設定',
            'currentPath' => '/resources',
            'entity' => $entity,
            'slot' => $slot,
            'selectedTargets' => $selectedTargets,
            'candidates' => $this->dependencySlotCandidates($slot, $this->entityRepository->listEntities(), $id),
            'error' => null,
        ]);

        return Response::html($html);
    }

    /**
     * @param array<string,string> $params
     */
    public function upsertDependencySlot(Request $request, array $params): Response
    {
        if (!$this->validateCsrf($request)) {
            return Response::html('CSRF トークンが一致しません。', 419);
        }

        $id = (string) ($params['id'] ?? '');
        $slotKey = (string) ($params['slot'] ?? '');
        $entity = $this->entityRepository->getEntity($id);
        if ($entity === null) {
            return Response::html('リソースが見つかりません。', 404);
        }

        $slot = $this->dependencySlotForEntity($entity, $slotKey);
        if ($slot === null) {
            return Response::html('依存関係スロットが見つかりません。', 404);
        }

        try {
            $rawTargets = $request->post('targets', []);
            $targets = is_array($rawTargets) ? $rawTargets : [$rawTargets];
            if (!(bool) ($slot['multiple'] ?? true)) {
                $targets = [trim((string) ($targets[0] ?? ''))];
            }

            $change = $this->changeService->createChange('human-ui', 'human');
            $this->changeService->addOperations((string) $change['id'], [
                'type' => 'set_dependency_slot',
                'resourceId' => $id,
                'slot' => $slotKey,
                'targets' => $targets,
            ]);
            $this->changeService->validateChange((string) $change['id']);

            return Response::redirect('/changes/' . rawurlencode((string) $change['id']));
        } catch (\Throwable $exception) {
            $html = $this->renderer->render('entities/dependency-slot-form', [
                'title' => '依存関係を設定',
                'currentPath' => '/resources',
                'entity' => $entity,
                'slot' => $slot,
                'selectedTargets' => is_array($request->post('targets', [])) ? $request->post('targets', []) : [],
                'candidates' => $this->dependencySlotCandidates($slot, $this->entityRepository->listEntities(), $id),
                'error' => $exception->getMessage(),
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
        $slotGroups = $this->groupDependenciesBySlots($id, $dependsOn, $usedBy, $dependencySlots, $this->entityRepository->listEntities());
        $normalizedTags = $this->normalizedTagsForRecord($record);
        $tagGroups = $this->groupTagsForDetail($normalizedTags);
        $softAssociations = $this->softAssociationTags($normalizedTags);

        $html = $this->renderer->render('entities/detail', [
            'title' => 'リソース: ' . $id,
            'currentPath' => '/resources',
            'entity' => $entity,
            'dependsOn' => $dependsOn,
            'usedBy' => $usedBy,
            'tagGroups' => $tagGroups,
            'softAssociations' => $softAssociations,
            'dependencySlotGroups' => $slotGroups,
        ]);

        return Response::html($html);
    }

    public function newEntityForm(Request $request): Response
    {
        $selectedType = (string) $request->query('schema', '');

        $html = $this->renderer->render('entities/form', [
            'title' => 'リソースを作成',
            'currentPath' => '/resources',
            'mode' => 'create',
            'error' => null,
            'viewModel' => $this->buildResourceFormViewModel(null, $selectedType),
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

        $html = $this->renderer->render('entities/form', [
            'title' => 'リソースを編集: ' . $id,
            'currentPath' => '/resources',
            'mode' => 'edit',
            'error' => null,
            'viewModel' => $this->buildResourceFormViewModel($entity, (string) $request->query('schema', '')),
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
            $html = $this->renderer->render('entities/form', [
                'title' => 'リソースフォーム',
                'currentPath' => '/resources',
                'mode' => $existingId !== null ? 'edit' : 'create',
                'error' => $exception->getMessage(),
                'viewModel' => $this->buildResourceFormViewModel($entity, (string) $request->post('type', ''), $request),
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
    public function saveChange(Request $request, array $params): Response
    {
        if (!$this->validateCsrf($request)) {
            return Response::html('CSRF トークンが一致しません。', 419);
        }

        $id = $params['id'] ?? '';

        try {
            $this->changeService->saveChange($id);

            return Response::redirect('/changes/' . rawurlencode($id));
        } catch (\Throwable $exception) {
            return Response::html('保存エラー: ' . $exception->getMessage(), 422);
        }
    }

    /**
     * @param array<string,string> $params
     */
    public function commitChange(Request $request, array $params): Response
    {
        return $this->saveChange($request, $params);
    }

    /**
     * @param array<string,string> $params
     */
    public function discardChange(Request $request, array $params): Response
    {
        if (!$this->validateCsrf($request)) {
            return Response::html('CSRF トークンが一致しません。', 419);
        }

        $id = $params['id'] ?? '';

        try {
            $this->changeService->discardChange($id);

            return Response::redirect('/changes/' . rawurlencode($id));
        } catch (\Throwable $exception) {
            return Response::html('破棄エラー: ' . $exception->getMessage(), 422);
        }
    }

    /**
     * @param array<string,string> $params
     */
    public function abortChange(Request $request, array $params): Response
    {
        return $this->discardChange($request, $params);
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

    public function settingsPage(Request $request): Response
    {
        $html = $this->renderer->render('settings/form', [
            'title' => '設定',
            'currentPath' => '/settings',
            'settings' => $this->settingsRepository->loadSettings(),
            'error' => null,
        ]);

        return Response::html($html);
    }

    public function upsertSettings(Request $request): Response
    {
        if (!$this->validateCsrf($request)) {
            return Response::html('CSRF トークンが一致しません。', 419);
        }

        try {
            $settings = $this->buildSettingsFromRequest($request);
            $change = $this->changeService->createChange('human-ui', 'human');
            $this->changeService->addOperations((string) $change['id'], [
                'type' => 'upsert_settings',
                'settings' => $settings,
            ]);
            $this->changeService->validateChange((string) $change['id']);

            return Response::redirect('/changes/' . rawurlencode((string) $change['id']));
        } catch (\Throwable $exception) {
            $html = $this->renderer->render('settings/form', [
                'title' => '設定',
                'currentPath' => '/settings',
                'settings' => $this->settingsRepository->loadSettings(),
                'error' => $exception->getMessage(),
            ]);

            return Response::html($html, 422);
        }
    }

    /**
     * @param array<string,mixed>|null $entity
     */
    private function buildResourceFormViewModel(?array $entity, string $selectedSchemaId = '', ?Request $request = null): ResourceFormViewModel
    {
        $record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
        $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
        $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
        $specJson = format_json($spec);

        $id = trim((string) ($metadata['id'] ?? ''));
        $type = trim((string) ($metadata['type'] ?? ($selectedSchemaId !== '' ? $selectedSchemaId : '')));
        $name = trim((string) ($metadata['name'] ?? ''));
        $sourcePath = trim((string) ($entity['sourcePath'] ?? ''));
        if ($request !== null) {
            $id = trim((string) $request->post('id', $id));
            $type = trim((string) $request->post('type', $type));
            $name = trim((string) $request->post('name', $name));
            $sourcePath = trim((string) $request->post('sourcePath', $sourcePath));
            $specJson = trim((string) $request->post('spec', $specJson));
        }

        $schemas = $this->activeSchemas();
        $schemaItems = [];
        $selectedSchema = null;
        foreach ($schemas as $schema) {
            if (($schema['kind'] ?? 'entity') === 'relation') {
                continue;
            }
            $schemaItems[] = $schema;
            if ((string) ($schema['id'] ?? '') === $type) {
                $selectedSchema = $schema;
            }
        }
        if ($selectedSchema === null && $selectedSchemaId !== '') {
            foreach ($schemaItems as $schemaItem) {
                if ((string) ($schemaItem['id'] ?? '') === $selectedSchemaId) {
                    $selectedSchema = $schemaItem;
                    $type = (string) ($schemaItem['id'] ?? $type);
                    break;
                }
            }
        }

        $normalizedTags = $this->normalizedTagsForRecord($record);
        if ($request !== null && is_array($request->post('basic_tag_key', null)) && is_array($request->post('basic_tag_value', null))) {
            $normalizedTags = [];
            $basicKeys = $request->post('basic_tag_key', []);
            $basicValues = $request->post('basic_tag_value', []);
            $count = min(count($basicKeys), count($basicValues));
            for ($i = 0; $i < $count; $i++) {
                $k = trim((string) ($basicKeys[$i] ?? ''));
                if ($k === '') {
                    continue;
                }
                $normalizedTags[$k] = trim((string) ($basicValues[$i] ?? ''));
            }
            if (is_array($request->post('tag_key', null)) && is_array($request->post('tag_value', null))) {
                $extraKeys = $request->post('tag_key', []);
                $extraValues = $request->post('tag_value', []);
                $extraCount = min(count($extraKeys), count($extraValues));
                for ($i = 0; $i < $extraCount; $i++) {
                    $k = trim((string) ($extraKeys[$i] ?? ''));
                    if ($k === '') {
                        continue;
                    }
                    $normalizedTags[$k] = trim((string) ($extraValues[$i] ?? ''));
                }
            }
        }

        $settings = $this->settingsRepository->loadSettings();
        $tagKeys = is_array($settings['tag_keys'] ?? null) ? $settings['tag_keys'] : [];
        $reservedPrefixes = is_array($settings['reserved_prefixes'] ?? null) ? $settings['reserved_prefixes'] : ['cataloga:'];
        $requiredTagKeys = is_array($selectedSchema['requiredTags'] ?? null) ? $selectedSchema['requiredTags'] : [];
        $recommendedTagKeys = is_array($selectedSchema['recommendedTags'] ?? null) ? $selectedSchema['recommendedTags'] : [];
        $basicTagKeys = $this->resolveManagementTagsForType((string) ($selectedSchema['id'] ?? $type), $settings, $selectedSchema);

        $settingsFields = [];
        foreach (($selectedSchema['properties'] ?? []) as $field => $def) {
            $fieldName = (string) $field;
            if (in_array($fieldName, ['environment', 'owner', 'site', 'zone', 'visibility', 'lifecycle', 'criticality', 'managed-by', 'cost-center', 'data-classification', 'backup-policy', 'patch-policy'], true)) {
                continue;
            }
            $value = $spec[$fieldName] ?? '';
            if ($request !== null && is_array($request->post('schema_fields', null))) {
                $schemaFields = $request->post('schema_fields', []);
                if (array_key_exists($fieldName, $schemaFields)) {
                    $value = $schemaFields[$fieldName];
                }
            }
            $settingsFields[] = [
                'name' => $fieldName,
                'type' => (string) ($def['type'] ?? 'string'),
                'format' => (string) ($def['format'] ?? ''),
                'enum' => is_array($def['enum'] ?? null) ? $def['enum'] : [],
                'value' => $value,
            ];
        }

        $basicTags = [];
        foreach ($basicTagKeys as $tagKey) {
            $tagConfig = is_array($tagKeys[$tagKey] ?? null) ? $tagKeys[$tagKey] : [];
            $basicTags[] = [
                'key' => $tagKey,
                'label' => (string) ($tagConfig['label'] ?? $tagKey),
                'required' => in_array($tagKey, $requiredTagKeys, true) || (bool) ($tagConfig['required'] ?? false),
                'values' => is_array($tagConfig['values'] ?? null) ? $tagConfig['values'] : [],
                'value' => (string) ($normalizedTags[$tagKey] ?? ''),
            ];
        }

        $additionalTags = [];
        foreach ($normalizedTags as $tagKey => $tagValue) {
            if (in_array($tagKey, $basicTagKeys, true)) {
                continue;
            }
            if ($this->isReservedTagKey((string) $tagKey, $reservedPrefixes)) {
                continue;
            }
            $additionalTags[] = ['key' => (string) $tagKey, 'value' => (string) $tagValue];
        }

        $formAction = $entity !== null && $id !== '' ? '/resources/' . rawurlencode($id) : '/resources';

        return new ResourceFormViewModel(
            $id,
            $type,
            $name,
            $sourcePath,
            $formAction,
            $schemaItems,
            $selectedSchema,
            $basicTags,
            $settingsFields,
            $additionalTags,
            $specJson,
            $entity === null && $selectedSchema === null
        );
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
        $dependencies = $this->decodeJsonObject(trim((string) $request->post('dependencies', '{}')), 'dependencies');
        $settings = $this->settingsRepository->loadSettings();
        $reservedPrefixes = is_array($settings['reserved_prefixes'] ?? null) ? $settings['reserved_prefixes'] : ['cataloga:'];
        $tags = $this->buildTagsFromRequest($request, $spec, $reservedPrefixes);

        return [
            'apiVersion' => 'cataloga.io/v2',
            'kind' => 'Resource',
            'metadata' => ['id' => $id, 'type' => $type, 'name' => $name, 'labels' => $labels, 'tags' => $tags],
            'spec' => $spec,
            'dependencies' => $dependencies,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildSettingsFromRequest(Request $request): array
    {
        $keys = is_array($request->post('tag_key', null)) ? $request->post('tag_key', []) : [];
        $labels = is_array($request->post('tag_label', null)) ? $request->post('tag_label', []) : [];
        $values = is_array($request->post('tag_values', null)) ? $request->post('tag_values', []) : [];
        $required = is_array($request->post('tag_required', null)) ? $request->post('tag_required', []) : [];
        $freeValue = is_array($request->post('tag_free_value', null)) ? $request->post('tag_free_value', []) : [];
        $allowEmpty = is_array($request->post('tag_allow_empty', null)) ? $request->post('tag_allow_empty', []) : [];

        $tagKeys = [];
        $count = count($keys);
        for ($i = 0; $i < $count; $i++) {
            $key = trim((string) ($keys[$i] ?? ''));
            if ($key === '') {
                continue;
            }
            if (!preg_match('/^[a-zA-Z0-9_.:-]+$/', $key)) {
                throw new \RuntimeException('Tag key contains unsupported characters: ' . $key);
            }
            $valueList = array_values(array_filter(array_map(
                static fn (string $value): string => trim($value),
                explode(',', (string) ($values[$i] ?? ''))
            ), static fn (string $value): bool => $value !== ''));

            $tagKeys[$key] = [
                'label' => trim((string) ($labels[$i] ?? $key)),
                'required' => in_array((string) $i, array_map('strval', $required), true),
                'values' => $valueList,
                'free_value' => in_array((string) $i, array_map('strval', $freeValue), true),
                'allow_empty' => in_array((string) $i, array_map('strval', $allowEmpty), true),
            ];
        }

        $reservedPrefixesRaw = trim((string) $request->post('reserved_prefixes', 'cataloga:'));
        $reservedPrefixes = array_values(array_filter(array_map('trim', explode(',', $reservedPrefixesRaw)), static fn (string $prefix): bool => $prefix !== ''));
        $defaultManagementTagsRaw = trim((string) $request->post('default_management_tags', 'environment, owner'));
        $defaultManagementTags = array_values(array_filter(array_map('trim', explode(',', $defaultManagementTagsRaw)), static fn (string $tag): bool => $tag !== ''));

        return [
            'version' => 1,
            'tag_keys' => $tagKeys,
            'default_management_tags' => $defaultManagementTags,
            'resource_type_profiles' => $this->settingsRepository->loadSettings()['resource_type_profiles'] ?? [],
            'reserved_prefixes' => $reservedPrefixes,
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
     * @param array<string,string> $tags
     * @return array<string,string>
     */
    private function softAssociationTags(array $tags): array
    {
        $hidden = ['environment', 'owner', 'site', 'zone', 'lifecycle', 'note', 'todo', 'risk'];
        $rows = [];
        foreach ($tags as $key => $value) {
            if (in_array($key, $hidden, true)) {
                continue;
            }
            if (trim((string) $value) === '') {
                continue;
            }
            $rows[$key] = $value;
        }
        ksort($rows);

        return $rows;
    }

    /**
     * @param array<string,mixed> $settings
     * @param array<string,mixed>|null $schema
     * @return array<int,string>
     */
    private function resolveManagementTagsForType(string $resourceType, array $settings, ?array $schema): array
    {
        $profiles = is_array($settings['resource_type_profiles'] ?? null) ? $settings['resource_type_profiles'] : [];
        $profile = is_array($profiles[$resourceType] ?? null) ? $profiles[$resourceType] : [];
        $workspaceTags = is_array($profile['management_tags'] ?? null) ? array_values(array_map('strval', $profile['management_tags'])) : [];
        if ($workspaceTags !== []) {
            return array_values(array_unique(array_filter(array_map('trim', $workspaceTags), static fn (string $v): bool => $v !== '')));
        }

        $recommended = is_array($schema['recommendedManagementTags'] ?? null) ? array_values(array_map('strval', $schema['recommendedManagementTags'])) : [];
        if ($recommended !== []) {
            return array_values(array_unique(array_filter(array_map('trim', $recommended), static fn (string $v): bool => $v !== '')));
        }

        $defaults = is_array($settings['default_management_tags'] ?? null) ? array_values(array_map('strval', $settings['default_management_tags'])) : [];
        if ($defaults !== []) {
            return array_values(array_unique(array_filter(array_map('trim', $defaults), static fn (string $v): bool => $v !== '')));
        }

        return ['environment', 'owner'];
    }

    /**
     * @param array<string,mixed> $settings
     * @param array<string,array<string,mixed>> $schemasById
     * @return array<string,array<string,mixed>>
     */
    private function resourceTypeProfilesByType(array $settings, array $schemasById): array
    {
        $profiles = is_array($settings['resource_type_profiles'] ?? null) ? $settings['resource_type_profiles'] : [];
        $items = [];
        foreach ($schemasById as $type => $schema) {
            $profile = is_array($profiles[$type] ?? null) ? $profiles[$type] : [];
            $items[$type] = [
                'type' => $type,
                'management_tags' => $this->resolveManagementTagsForType($type, $settings, $schema),
                'list_columns' => is_array($profile['list_columns'] ?? null) ? $profile['list_columns'] : [],
            ];
        }
        ksort($items);

        return $items;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,string>
     */
    private function buildTypeSpecificComputedColumns(array $row): array
    {
        $record = is_array($row['record'] ?? null) ? $row['record'] : [];
        $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
        $value = static fn (string $key): string => trim((string) ($spec[$key] ?? ''));
        return [
            'status' => (string) ($row['status'] ?? ''),
            'record_type' => $value('record_type') !== '' ? $value('record_type') : $value('type'),
            'value' => $value('value'),
            'vlan_id' => $value('vlan_id'),
            'cidr' => $value('cidr'),
            'os' => $value('os') !== '' ? $value('os') : $value('os_family'),
            'ip' => $value('ip') !== '' ? $value('ip') : $value('ip_address'),
            'runtime' => $value('runtime'),
            'port' => $value('port'),
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
     * @param array<string,mixed> $entity
     * @return array<string,mixed>|null
     */
    private function dependencySlotForEntity(array $entity, string $slotKey): ?array
    {
        $record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
        $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
        $resourceType = (string) ($metadata['type'] ?? '');
        $schema = $this->activeEntitySchemasById()[$resourceType] ?? null;
        $slots = is_array($schema['dependencySlots'] ?? null) ? $schema['dependencySlots'] : [];
        foreach ($slots as $slot) {
            if ((string) ($slot['key'] ?? '') === $slotKey) {
                if ((string) ($slot['direction'] ?? 'outgoing') !== 'outgoing') {
                    return null;
                }
                return $slot;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $slot
     * @param array<int,array<string,mixed>> $entities
     * @return array<int,array<string,mixed>>
     */
    private function dependencySlotCandidates(array $slot, array $entities, string $selfId): array
    {
        $targetTypes = is_array($slot['target_types'] ?? null) ? $slot['target_types'] : [];
        $candidates = [];
        foreach ($entities as $entity) {
            if ((string) ($entity['id'] ?? '') === $selfId) {
                continue;
            }
            $type = (string) ($entity['type'] ?? '');
            if ($targetTypes !== [] && !in_array($type, $targetTypes, true)) {
                continue;
            }
            $candidates[] = $entity;
        }

        return $candidates;
    }

    /**
     * @param array<int,array<string,mixed>> $dependsOn
     * @param array<int,array<string,mixed>> $usedBy
     * @param array<int,array<string,mixed>> $slots
     * @param array<int,array<string,mixed>> $entities
     * @return array<string,mixed>
     */
    private function groupDependenciesBySlots(string $resourceId, array $dependsOn, array $usedBy, array $slots, array $entities): array
    {
        $entityMap = [];
        foreach ($entities as $entity) {
            $entityMap[(string) ($entity['id'] ?? '')] = $entity;
        }

        if ($slots === []) {
            return ['slots' => [], 'other' => array_merge($dependsOn, $usedBy)];
        }

        $grouped = [];
        $assigned = [];
        foreach ($slots as $slot) {
            $slotKey = (string) ($slot['key'] ?? '');
            $relationType = (string) ($slot['relation_type'] ?? '');
            $direction = (string) ($slot['direction'] ?? 'outgoing');
            if ($slotKey === '' || $relationType === '' || $direction !== 'outgoing') {
                continue;
            }

            $items = [];
            $pool = $direction === 'incoming' ? $usedBy : $dependsOn;
            foreach ($pool as $relation) {
                $currentRelationType = (string) ($relation['type'] ?? '');
                if ($currentRelationType !== $relationType && $currentRelationType !== $slotKey) {
                    continue;
                }
                $relationId = (string) ($relation['id'] ?? '');
                if ($relationId !== '') {
                    $assigned[$relationId] = true;
                }
                $peerId = $direction === 'incoming' ? (string) ($relation['from'] ?? '') : (string) ($relation['to'] ?? '');
                $peer = is_array($entityMap[$peerId] ?? null) ? $entityMap[$peerId] : [];
                $peerRecord = is_array($peer['record'] ?? null) ? $peer['record'] : [];
                $peerMetadata = is_array($peerRecord['metadata'] ?? null) ? $peerRecord['metadata'] : [];
                $peerTags = $this->normalizedTagsForRecord($peerRecord);
                $items[] = [
                    'relation' => $relation,
                    'peer_id' => $peerId,
                    'peer_name' => (string) ($peerMetadata['name'] ?? $peerId),
                    'peer_type' => (string) ($peerMetadata['type'] ?? ''),
                    'peer_environment' => (string) ($peerTags['environment'] ?? ''),
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
