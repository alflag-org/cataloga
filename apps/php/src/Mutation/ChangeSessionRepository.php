<?php

declare(strict_types=1);

namespace Cataloga\Mutation;

final class ChangeSessionRepository
{
    private readonly string $changesRoot;

    public function __construct(private readonly string $runtimeRoot)
    {
        $this->changesRoot = rtrim($this->runtimeRoot, '/') . '/changes';
    }

    /**
     * @return array<string,mixed>
     */
    public function create(string $actor, string $actorType): array
    {
        $this->ensureChangesRoot();
        $id = gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));

        $timestamp = gmdate(DATE_ATOM);
        $session = [
            'id' => $id,
            'actor' => $actor,
            'actorType' => $actorType,
            'status' => 'draft',
            'createdAt' => $timestamp,
            'updatedAt' => $timestamp,
            'operations' => [],
            'validation' => [
                'ranAt' => null,
                'valid' => false,
                'errors' => [],
                'warnings' => [],
            ],
            'commitHash' => null,
        ];

        $this->save($session);

        return $session;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function get(string $id): ?array
    {
        $path = $this->sessionPath($id);
        if (!is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Failed to read change session: ' . $id);
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Change session JSON is invalid: ' . $id);
        }

        return $decoded;
    }

    /**
     * @param array<string,mixed> $session
     */
    public function save(array $session): void
    {
        $id = (string) ($session['id'] ?? '');
        if ($id === '') {
            throw new \RuntimeException('Change session id is missing.');
        }

        $this->ensureChangesRoot();

        $directory = $this->changesRoot . '/' . $id;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create change session directory: ' . $directory);
        }

        $session['updatedAt'] = gmdate(DATE_ATOM);

        $json = json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode change session JSON.');
        }

        if (file_put_contents($this->sessionPath($id), $json . PHP_EOL) === false) {
            throw new \RuntimeException('Failed to persist change session: ' . $id);
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listRecent(int $limit = 20): array
    {
        $this->ensureChangesRoot();

        $sessions = [];
        $directories = glob($this->changesRoot . '/*', GLOB_ONLYDIR) ?: [];
        rsort($directories);

        foreach ($directories as $directory) {
            $id = basename($directory);
            $session = $this->get($id);
            if ($session === null) {
                continue;
            }

            $sessions[] = $session;
            if (count($sessions) >= $limit) {
                break;
            }
        }

        return $sessions;
    }

    private function sessionPath(string $id): string
    {
        return $this->changesRoot . '/' . $id . '/session.json';
    }

    private function ensureChangesRoot(): void
    {
        if (!is_dir($this->changesRoot) && !mkdir($this->changesRoot, 0775, true) && !is_dir($this->changesRoot)) {
            throw new \RuntimeException('Unable to create changes root: ' . $this->changesRoot);
        }
    }
}
