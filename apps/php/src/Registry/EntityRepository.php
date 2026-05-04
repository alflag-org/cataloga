<?php

declare(strict_types=1);

namespace Cataloga\Registry;

final class EntityRepository
{
    private const REGISTRY_DIRECTORIES = ['schemas', 'entities', 'relations', 'views', 'policies', 'evidence'];

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

            $metadata = $record['metadata'] ?? [];
            $id = is_array($metadata) ? (string) ($metadata['id'] ?? '') : '';
            if ($id === '') {
                $parseErrors[] = ['path' => $relativePath, 'message' => 'metadata.id is required.'];
                continue;
            }

            $idPaths[$id] ??= [];
            $idPaths[$id][] = $relativePath;

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
        $normalized = $this->pathGuard->normalizeEntityPath($sourcePath);
        $absolutePath = $this->absolutePath($normalized);
        $directory = dirname($absolutePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Failed to create directory: ' . $directory);
        }

        $content = $this->recordSerializer->encode($record, $normalized);
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


    /**
     * @return array<int,array{path:string,record:array<string,mixed>}>
     */
    public function loadSchemaRecords(): array
    {
        $records = [];

        foreach ($this->schemaFiles() as $entry) {
            try {
                $record = $this->recordParser->parseFile($entry['absolute']);
                if (is_array($record)) {
                    $records[] = [
                        'path' => $entry['relative'],
                        'record' => $record,
                    ];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $records;
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
        $directory = $this->registryRoot . '/entities';
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
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

            $files[] = $fileInfo->getPathname();
        }

        sort($files);

        return $files;
    }
    /**
     * @return array<int,array{absolute:string,relative:string}>
     */
    private function schemaFiles(): array
    {
        $roots = [
            [
                'absolute' => $this->registryRoot . '/schemas',
                'relativePrefix' => 'schemas',
            ],
        ];

        $projectRoot = dirname($this->registryRoot);
        $domainPacksRoot = $projectRoot . '/domain-packs';
        if (is_dir($domainPacksRoot)) {
            $packs = array_filter(scandir($domainPacksRoot) ?: [], static fn (string $name): bool => $name !== '.' && $name !== '..');
            foreach ($packs as $packName) {
                $schemaDir = $domainPacksRoot . '/' . $packName . '/schemas';
                $roots[] = [
                    'absolute' => $schemaDir,
                    'relativePrefix' => 'domain-packs/' . $packName . '/schemas',
                ];
            }
        }

        $files = [];
        foreach ($roots as $root) {
            if (!is_dir($root['absolute'])) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root['absolute'], \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                    continue;
                }

                $extension = strtolower($fileInfo->getExtension());
                if (!in_array($extension, ['yaml', 'yml', 'json'], true)) {
                    continue;
                }

                $absolutePath = $fileInfo->getPathname();
                $relativeWithinRoot = ltrim(substr($absolutePath, strlen(rtrim($root['absolute'], '/'))), '/');
                $files[] = [
                    'absolute' => $absolutePath,
                    'relative' => $root['relativePrefix'] . ($relativeWithinRoot === '' ? '' : '/' . $relativeWithinRoot),
                ];
            }
        }

        usort($files, static fn (array $a, array $b): int => strcmp($a['relative'], $b['relative']));

        return $files;
    }

}
