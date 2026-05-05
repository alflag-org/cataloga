<?php

declare(strict_types=1);

namespace Cataloga\Registry;

final class EntityRepository
{
    private const REGISTRY_DIRECTORIES = ['schemas', 'resources', 'entities', 'relations', 'views', 'policies', 'evidence'];

    public function __construct(
        private readonly string $registryRoot,
        private readonly RecordParser $recordParser,
        private readonly RecordSerializer $recordSerializer,
        private readonly PathGuard $pathGuard,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function loadEntityIndex(): array
    {
        $byId = [];
        $idPaths = [];
        $parseErrors = [];

        foreach ($this->entityFiles() as $absolutePath) {
            $relativePath = $this->toRelativePath($absolutePath);

            try {
                $record = $this->recordParser->parseFile($absolutePath);
            } catch (\Throwable $exception) {
                $parseErrors[] = ['path' => $relativePath, 'message' => $exception->getMessage()];
                continue;
            }

            $record = $this->canonicalize($record);
            $metadata = $record['metadata'] ?? [];
            $id = is_array($metadata) ? (string) ($metadata['id'] ?? '') : '';
            if ($id === '') {
                $parseErrors[] = ['path' => $relativePath, 'message' => 'metadata.id is required.'];
                continue;
            }

            $idPaths[$id] ??= [];
            if (!in_array($relativePath, $idPaths[$id], true)) {
                $idPaths[$id][] = $relativePath;
            }

            $byId[$id] = [
                'record' => $record,
                'sourcePath' => $relativePath,
            ];
        }

        return [
            'byId' => $byId,
            'idPaths' => $idPaths,
            'parseErrors' => $parseErrors,
        ];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function listEntities(): array
    {
        $index = $this->loadEntityIndex();
        $entities = [];

        foreach ($index['byId'] as $id => $payload) {
            $record = $payload['record'];
            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];

            $entities[] = [
                'id' => $id,
                'type' => (string) ($metadata['type'] ?? ''),
                'name' => (string) ($metadata['name'] ?? ''),
                'sourcePath' => $payload['sourcePath'],
                'record' => $record,
            ];
        }

        usort($entities, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        return $entities;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getEntity(string $id): ?array
    {
        $index = $this->loadEntityIndex();

        return $index['byId'][$id] ?? null;
    }

    public function writeEntity(array $record, string $sourcePath): void
    {
        $normalized = $this->pathGuard->normalizeResourcePath($sourcePath);
        $absolutePath = $this->absolutePath($normalized);
        $directory = dirname($absolutePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Failed to create directory: ' . $directory);
        }

        $content = $this->recordSerializer->encode($this->canonicalize($record, true), $normalized);
        if (file_put_contents($absolutePath, $content) === false) {
            throw new \RuntimeException('Failed to write entity file: ' . $normalized);
        }
    }

    /**
     * @param array<int,string> $paths
     */
    public function deleteEntityPaths(array $paths): void
    {
        foreach ($paths as $path) {
            $normalized = $this->pathGuard->normalizeEntityPath($path);
            $absolute = $this->absolutePath($normalized);

            if (is_file($absolute) && !unlink($absolute)) {
                throw new \RuntimeException('Failed to delete entity file: ' . $normalized);
            }
        }
    }

    public function absolutePathForEntity(string $path): string
    {
        $normalized = $this->pathGuard->normalizeEntityPath($path);

        return $this->absolutePath($normalized);
    }

    /**
     * @return array<string,mixed>
     */
    public function scanRegistryRecords(): array
    {
        $records = [];
        $parseErrors = [];

        foreach (self::REGISTRY_DIRECTORIES as $directory) {
            $absolute = $this->registryRoot . '/' . $directory;
            if (!is_dir($absolute)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS)
            );

            $bucket = [];
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                    continue;
                }

                $extension = strtolower($fileInfo->getExtension());
                if (!in_array($extension, ['yaml', 'yml', 'json'], true)) {
                    continue;
                }

                $relativePath = $this->toRelativePath($fileInfo->getPathname());
                try {
                    $record = $this->recordParser->parseFile($fileInfo->getPathname());
                    $records[] = ['path' => $relativePath, 'record' => $record];
                } catch (\Throwable $exception) {
                    $parseErrors[] = ['path' => $relativePath, 'message' => $exception->getMessage()];
                }
            }
        }

        return [
            'records' => $records,
            'parseErrors' => $parseErrors,
        ];
    }

    private function absolutePath(string $relativePath): string
    {
        return rtrim($this->registryRoot, '/') . '/' . ltrim($relativePath, '/');
    }

    private function toRelativePath(string $absolutePath): string
    {
        $root = rtrim($this->registryRoot, '/') . '/';

        return str_starts_with($absolutePath, $root)
            ? substr($absolutePath, strlen($root))
            : $absolutePath;
    }

    /**
     * @return array<int,string>
     */
    private function entityFiles(): array
    {
        $files = [];
        foreach (['resources', 'entities'] as $topDirectory) {
            $directory = $this->registryRoot . '/' . $topDirectory;
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                    continue;
                }

                $extension = strtolower($fileInfo->getExtension());
                if (!in_array($extension, ['yaml', 'yml', 'json'], true)) {
                    continue;
                }

                $bucket[] = $fileInfo->getPathname();
            }
            sort($bucket);
            array_push($files, ...$bucket);
        }

        return $files;
    }

    /**
     * @param array<string,mixed> $record
     * @return array<string,mixed>
     */
    private function canonicalize(array $record, bool $asResource = false): array
    {
        $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
        $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
        $dependencies = is_array($record['dependencies'] ?? null) ? $record['dependencies'] : [];

        return [
            'apiVersion' => (string) ($record['apiVersion'] ?? 'cataloga.io/v2'),
            'kind' => $asResource ? 'Resource' : (string) ($record['kind'] ?? 'Resource'),
            'metadata' => [
                'id' => (string) ($metadata['id'] ?? ''),
                'type' => (string) ($metadata['type'] ?? ''),
                'name' => (string) ($metadata['name'] ?? ''),
                'labels' => is_array($metadata['labels'] ?? null) ? $metadata['labels'] : [],
                'tags' => is_array($metadata['tags'] ?? null) ? $metadata['tags'] : [],
            ],
            'spec' => $spec,
            'dependencies' => $this->normalizeDependencies($dependencies),
        ];
    }

    /**
     * @param array<string,mixed> $dependencies
     * @return array<string,array<int,string>>
     */
    private function normalizeDependencies(array $dependencies): array
    {
        $normalized = [];
        foreach ($dependencies as $slot => $targets) {
            $slotKey = trim((string) $slot);
            if ($slotKey === '') {
                continue;
            }
            $targetList = is_array($targets) ? $targets : [$targets];
            foreach ($targetList as $target) {
                if (!is_scalar($target) && $target !== null) {
                    continue;
                }
                $targetId = trim((string) ($target ?? ''));
                if ($targetId === '') {
                    continue;
                }
                $normalized[$slotKey][] = $targetId;
            }
            if (isset($normalized[$slotKey])) {
                $normalized[$slotKey] = array_values(array_unique($normalized[$slotKey]));
            }
        }
        ksort($normalized);

        return $normalized;
    }
}
