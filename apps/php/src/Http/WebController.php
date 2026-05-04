<?php

declare(strict_types=1);

namespace Cataloga\Http;

use Cataloga\Git\GitService;
use Cataloga\Mutation\ChangeService;
use Cataloga\Registry\EntityRepository;
use Cataloga\View\TemplateRenderer;

final class WebController
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly EntityRepository $entityRepository,
        private readonly ChangeService $changeService,
        private readonly GitService $gitService,
    ) {
    }

    public function dashboard(Request $request): Response
    {
        $entities = $this->entityRepository->listEntities();
        $changes = $this->changeService->listRecentChanges(10);
        $gitStatus = $this->gitService->statusShort();

        $html = $this->renderer->render('dashboard', [
            'title' => 'Dashboard',
            'currentPath' => '/',
            'entityCount' => count($entities),
            'changes' => $changes,
            'gitStatus' => $gitStatus,
        ]);

        return Response::html($html);
    }

    public function entityList(Request $request): Response
    {
        $html = $this->renderer->render('entities/list', [
            'title' => 'Entities',
            'currentPath' => '/entities',
            'entities' => $this->entityRepository->listEntities(),
        ]);

        return Response::html($html);
    }

    public function changeList(Request $request): Response
    {
        $html = $this->renderer->render('changes/list', [
            'title' => 'Changes',
            'currentPath' => '/changes',
            'changes' => $this->changeService->listRecentChanges(50),
        ]);

        return Response::html($html);
    }

    /**
     * @param array<string,string> $params
     */
    public function entityDetail(Request $request, array $params): Response
    {
        $id = $params['id'] ?? '';
        $entity = $this->entityRepository->getEntity($id);
        if ($entity === null) {
            return Response::html('Entity not found', 404);
        }

        $html = $this->renderer->render('entities/detail', [
            'title' => 'Entity ' . $id,
            'currentPath' => '/entities',
            'entity' => $entity,
        ]);

        return Response::html($html);
    }

    public function newEntityForm(Request $request): Response
    {
        $html = $this->renderer->render('entities/form', [
            'title' => 'Create Entity',
            'currentPath' => '/entities',
            'mode' => 'create',
            'entity' => null,
            'error' => null,
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
            return Response::html('Entity not found', 404);
        }

        $html = $this->renderer->render('entities/form', [
            'title' => 'Edit Entity ' . $id,
            'currentPath' => '/entities',
            'mode' => 'edit',
            'entity' => $entity,
            'error' => null,
        ]);

        return Response::html($html);
    }

    /**
     * @param array<string,string> $params
     */
    public function upsertEntity(Request $request, array $params = []): Response
    {
        if (!$this->validateCsrf($request)) {
            return Response::html('CSRF token mismatch.', 419);
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
            $this->changeService->validateChange((string) $change['id']);

            return Response::redirect('/changes/' . rawurlencode((string) $change['id']));
        } catch (\Throwable $exception) {
            $existingId = $params['id'] ?? null;
            $entity = $existingId !== null ? $this->entityRepository->getEntity($existingId) : null;
            $html = $this->renderer->render('entities/form', [
                'title' => 'Entity Form',
                'currentPath' => '/entities',
                'mode' => $existingId !== null ? 'edit' : 'create',
                'entity' => $entity,
                'error' => $exception->getMessage(),
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
            return Response::html('Change session not found', 404);
        }

        $diff = $this->changeService->diffChange($id);

        $html = $this->renderer->render('changes/detail', [
            'title' => 'Change ' . $id,
            'currentPath' => '/changes/' . rawurlencode($id),
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
            return Response::html('CSRF token mismatch.', 419);
        }

        $id = $params['id'] ?? '';

        try {
            $this->changeService->validateChange($id);
            return Response::redirect('/changes/' . rawurlencode($id));
        } catch (\Throwable $exception) {
            return Response::html('Validation error: ' . $exception->getMessage(), 422);
        }
    }

    /**
     * @param array<string,string> $params
     */
    public function commitChange(Request $request, array $params): Response
    {
        if (!$this->validateCsrf($request)) {
            return Response::html('CSRF token mismatch.', 419);
        }

        $id = $params['id'] ?? '';

        try {
            $message = (string) $request->post('commitMessage', '');
            $createGitCommit = $request->post('createGitCommit', '1') === '1';
            $this->changeService->commitChange($id, $message, $createGitCommit);

            return Response::redirect('/changes/' . rawurlencode($id));
        } catch (\Throwable $exception) {
            return Response::html('Commit error: ' . $exception->getMessage(), 422);
        }
    }

    /**
     * @param array<string,string> $params
     */
    public function abortChange(Request $request, array $params): Response
    {
        if (!$this->validateCsrf($request)) {
            return Response::html('CSRF token mismatch.', 419);
        }

        $id = $params['id'] ?? '';

        try {
            $this->changeService->abortChange($id);

            return Response::redirect('/changes/' . rawurlencode($id));
        } catch (\Throwable $exception) {
            return Response::html('Abort error: ' . $exception->getMessage(), 422);
        }
    }

    public function validationPage(Request $request): Response
    {
        $result = $this->changeService->validateCurrentRegistry();
        $html = $this->renderer->render('validation/index', [
            'title' => 'Validation',
            'currentPath' => '/validation',
            'result' => $result,
        ]);

        return Response::html($html);
    }

    public function gitDiffPage(Request $request): Response
    {
        $diff = $this->gitService->diffRegistryAndCataloga();

        $html = $this->renderer->render('git/diff', [
            'title' => 'Git Diff',
            'currentPath' => '/git/diff',
            'diff' => $diff,
        ]);

        return Response::html($html);
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
        $id = trim((string) $request->post('id', ''));
        $type = trim((string) $request->post('type', ''));
        $name = trim((string) $request->post('name', ''));
        $labelsJson = trim((string) $request->post('labels', '{}'));
        $tagsRaw = trim((string) $request->post('tags', ''));
        $specJson = trim((string) $request->post('spec', '{}'));

        $labels = $this->decodeJsonObject($labelsJson, 'labels');
        $spec = $this->decodeJsonObject($specJson, 'spec');

        $tags = [];
        if ($tagsRaw !== '') {
            $tags = array_values(array_filter(array_map('trim', explode(',', $tagsRaw)), static fn (string $tag): bool => $tag !== ''));
        }

        return [
            'apiVersion' => 'cataloga.io/v2',
            'kind' => 'Entity',
            'metadata' => [
                'id' => $id,
                'type' => $type,
                'name' => $name,
                'labels' => $labels,
                'tags' => $tags,
            ],
            'spec' => $spec,
        ];
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
}
