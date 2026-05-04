<?php

declare(strict_types=1);

namespace Cataloga\Http;

use Cataloga\Mutation\ChangeService;
use Cataloga\Registry\EntityRepository;
use Cataloga\Registry\RelationRepository;

final class ApiController
{
    public function __construct(
        private readonly EntityRepository $entityRepository,
        private readonly RelationRepository $relationRepository,
        private readonly ChangeService $changeService,
    ) {
    }

    public function relations(Request $request): Response
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

    public function relations(Request $request): Response
    {
        return $this->listRecordsByType('relation');
    }

    public function schemas(Request $request): Response
    {
        return $this->listRecordsByType('schema');
    }

    /**
     * @param array<string,string> $params
     */
    public function entityNeighbors(Request $request, array $params): Response
    {
        $id = $params['id'] ?? '';
        $entity = $this->entityRepository->getEntity($id);
        if ($entity === null) {
            return Response::json(['error' => 'Entity not found', 'id' => $id], 404);
        }

        $scan = $this->entityRepository->scanRegistryRecords();
        $neighbors = [];
        $relatedRelations = [];

        foreach ($scan['records'] as $item) {
            $record = $item['record'];
            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
            if ((string) ($metadata['type'] ?? '') !== 'relation') {
                continue;
            }

            $from = (string) ($record['from'] ?? '');
            $to = (string) ($record['to'] ?? '');
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
                'id' => (string) ($metadata['id'] ?? ''),
                'type' => 'relation',
                'path' => $item['path'],
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
            'counts' => [
                'neighbors' => count($neighbors),
                'relations' => count($relatedRelations),
                'errors' => count($scan['parseErrors']),
            ],
            'errors' => $scan['parseErrors'],
        ]);
    }

    public function search(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $needle = strtolower($query);
        $scan = $this->entityRepository->scanRegistryRecords();
        $items = [];

        foreach ($scan['records'] as $item) {
            $record = $item['record'];
            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
            $id = (string) ($metadata['id'] ?? '');
            $type = (string) ($metadata['type'] ?? '');
            $name = (string) ($metadata['name'] ?? '');

            $haystack = strtolower($id . ' ' . $type . ' ' . $name . ' ' . $item['path'] . ' ' . json_encode($record));
            if ($needle !== '' && !str_contains($haystack, $needle)) {
                continue;
            }

            $items[] = [
                'id' => $id,
                'type' => $type,
                'path' => $item['path'],
                'name' => $name,
            ];
        }

        return Response::json([
            'query' => $query,
            'items' => $items,
            'counts' => [
                'items' => count($items),
                'errors' => count($scan['parseErrors']),
            ],
            'errors' => $scan['parseErrors'],
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
            return Response::json(['error' => 'Change session not found', 'id' => $id], 404);
        }

        $operations = is_array($session['operations'] ?? null) ? $session['operations'] : [];
        $validation = is_array($session['validation'] ?? null) ? $session['validation'] : [];
        $errors = is_array($validation['errors'] ?? null) ? $validation['errors'] : [];

        return Response::json([
            'id' => (string) ($session['id'] ?? $id),
            'type' => 'change_session',
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

    private function listRecordsByType(string $type): Response
    {
        $scan = $this->entityRepository->scanRegistryRecords();
        $items = [];
        foreach ($scan['records'] as $item) {
            $record = $item['record'];
            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
            if ((string) ($metadata['type'] ?? '') !== $type) {
                continue;
            }

            $items[] = [
                'id' => (string) ($metadata['id'] ?? ''),
                'type' => $type,
                'path' => $item['path'],
                'record' => $record,
            ];
        }

        return Response::json([
            'items' => $items,
            'counts' => ['items' => count($items), 'errors' => count($scan['parseErrors'])],
            'errors' => $scan['parseErrors'],
        ]);
    }
}
