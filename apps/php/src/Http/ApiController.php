<?php

declare(strict_types=1);

namespace Cataloga\Http;

use Cataloga\Mutation\ChangeService;
use Cataloga\Registry\EntityRepository;

final class ApiController
{
    public function __construct(
        private readonly EntityRepository $entityRepository,
        private readonly ChangeService $changeService,
    ) {
    }

    public function entities(Request $request): Response
    {
        return Response::json([
            'items' => $this->entityRepository->listEntities(),
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
            return Response::json(['error' => 'Entity not found'], 404);
        }

        return Response::json($entity);
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
            return Response::json(['error' => 'Change session not found'], 404);
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
            $createGitCommit = $this->parseBoolean($payload['createGitCommit'] ?? true);

            $session = $this->changeService->commitChange($id, $message, $createGitCommit);

            return Response::json($session);
        } catch (\Throwable $exception) {
            return Response::json(['error' => $exception->getMessage()], 422);
        }
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
}
