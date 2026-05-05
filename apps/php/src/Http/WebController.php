<?php

declare(strict_types=1);

namespace Cataloga\Http;

use Cataloga\Git\GitService;
use Cataloga\Mutation\ChangeService;
use Cataloga\Registry\DomainPackRepository;
use Cataloga\Registry\EntityRepository;
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
            if (!in_array($status, ['committed', 'aborted'], true)) {
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

        $entities = $this->entityRepository->listEntities();
        $filtered = [];
        $types = [];

        foreach ($entities as $entity) {
            $record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
            $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];

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

            $filtered[] = [
                'id' => (string) ($entity['id'] ?? ''),
                'name' => (string) ($entity['name'] ?? ''),
                'type' => $resourceType,
                'environment' => (string) ($spec['environment'] ?? ''),
                'owner' => (string) ($spec['owner'] ?? ''),
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
            'filters' => ['q' => $query, 'type' => $type],
            'types' => array_keys($types),
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
        $html = $this->renderer->render('relations/form', [
            'title' => '依存関係を作成',
            'currentPath' => '/dependencies',
            'mode' => 'create',
            'relation' => null,
            'error' => null,
            'entities' => $this->entityRepository->listEntities(),
            'relationTypes' => $this->listRelationTypes(),
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

        $html = $this->renderer->render('relations/form', [
            'title' => '依存関係を編集: ' . $id,
            'currentPath' => '/dependencies',
            'mode' => 'edit',
            'relation' => $relation,
            'error' => null,
            'entities' => $this->entityRepository->listEntities(),
            'relationTypes' => $this->listRelationTypes(),
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
            $html = $this->renderer->render('relations/form', [
                'title' => '依存関係フォーム',
                'currentPath' => '/dependencies',
                'mode' => $existingId !== null ? 'edit' : 'create',
                'relation' => $relation,
                'error' => $exception->getMessage(),
                'entities' => $this->entityRepository->listEntities(),
                'relationTypes' => $this->listRelationTypes(),
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

        $html = $this->renderer->render('entities/detail', [
            'title' => 'リソース: ' . $id,
            'currentPath' => '/resources',
            'entity' => $entity,
            'dependsOn' => $dependsOn,
            'usedBy' => $usedBy,
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
            'entity' => null,
            'error' => null,
            'schemas' => $this->activeSchemas(),
            'selectedSchemaId' => $selectedType,
            'entities' => $this->entityRepository->listEntities(),
            'relationTypes' => $this->listRelationTypes(),
            'fieldErrors' => [],
            'yamlPreview' => null,
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
            'entity' => $entity,
            'error' => null,
            'schemas' => $this->activeSchemas(),
            'selectedSchemaId' => (string) $request->query('schema', ''),
            'entities' => $this->entityRepository->listEntities(),
            'relationTypes' => $this->listRelationTypes(),
            'fieldErrors' => [],
            'yamlPreview' => null,
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
                'entity' => $entity,
                'error' => $exception->getMessage(),
                'schemas' => $this->activeSchemas(),
                'selectedSchemaId' => (string) $request->post('type', ''),
                'entities' => $this->entityRepository->listEntities(),
                'relationTypes' => $this->listRelationTypes(),
                'fieldErrors' => [],
                'yamlPreview' => null,
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
            $createGitCommit = $request->post('createGitCommit', '1') === '1';
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
        $tagsRaw = trim((string) $request->post('tags', ''));
        $tags = $tagsRaw !== '' ? array_values(array_filter(array_map('trim', explode(',', $tagsRaw)), static fn (string $t): bool => $t !== '')) : [];
        $spec = $advanced ? $this->decodeJsonObject(trim((string) $request->post('spec', '{}')), 'spec') : $this->buildSpecFromSchema($request);

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

        $types = $request->post('dependency_type', []);
        $targets = $request->post('dependency_target', []);
        if (!is_array($types) || !is_array($targets)) {
            return [];
        }

        $operations = [];
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
