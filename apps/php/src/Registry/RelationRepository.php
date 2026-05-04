<?php

declare(strict_types=1);

namespace Cataloga\Registry;

final class RelationRepository
{
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
    public function loadRelationIndex(): array
    {
        $byId = [];
        $idPaths = [];
        $parseErrors = [];

        foreach ($this->relationFiles() as $absolutePath) {
            $relativePath = $this->toRelativePath($absolutePath);

            try {
                $record = $this->recordParser->parseFile($absolutePath);
            } catch (\Throwable $exception) {
                $parseErrors[] = ['path' => $relativePath, 'message' => $exception->getMessage()];
                continue;
            }

            $id = is_string($record['id'] ?? null) ? (string) $record['id'] : '';
            if ($id === '') {
                $parseErrors[] = ['path' => $relativePath, 'message' => 'relation id is required.'];
                continue;
            }

            $idPaths[$id] ??= [];
            $idPaths[$id][] = $relativePath;

            $byId[$id] = [
                'record' => $record,
                'sourcePath' => $relativePath,
            ];
        }

        return ['byId' => $byId, 'idPaths' => $idPaths, 'parseErrors' => $parseErrors];
    }

    /** @return array<int,array<string,mixed>> */
    public function listRelations(): array
    {
        $index = $this->loadRelationIndex();
        $relations = [];
        foreach ($index['byId'] as $id => $payload) {
            $record = is_array($payload['record'] ?? null) ? $payload['record'] : [];
            $relations[] = [
                'id' => $id,
                'source' => (string) ($record['source'] ?? ''),
                'target' => (string) ($record['target'] ?? ''),
                'type' => (string) ($record['type'] ?? ''),
                'sourcePath' => (string) ($payload['sourcePath'] ?? ''),
                'record' => $record,
            ];
        }

        usort($relations, static fn (array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']));
        return $relations;
    }

    /** @return array<string,mixed>|null */
    public function getRelation(string $id): ?array
    {
        $index = $this->loadRelationIndex();
        return $index['byId'][$id] ?? null;
    }

    public function writeRelation(array $record, string $sourcePath): void
    {
        $normalized = $this->pathGuard->normalizeWithinRegistry($sourcePath, 'relations');
        $absolutePath = $this->absolutePath($normalized);
        $directory = dirname($absolutePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Failed to create directory: ' . $directory);
        }

        $content = $this->recordSerializer->encode($record, $normalized);
        if (file_put_contents($absolutePath, $content) === false) {
            throw new \RuntimeException('Failed to write relation file: ' . $normalized);
        }
    }

    /** @param array<int,string> $paths */
    public function deleteRelationPaths(array $paths): void
    {
        foreach ($paths as $path) {
            $normalized = $this->pathGuard->normalizeWithinRegistry($path, 'relations');
            $absolute = $this->absolutePath($normalized);
            if (is_file($absolute) && !unlink($absolute)) {
                throw new \RuntimeException('Failed to delete relation file: ' . $normalized);
            }
        }
    }

    public function absolutePathForRelation(string $path): string
    {
        $normalized = $this->pathGuard->normalizeWithinRegistry($path, 'relations');
        return $this->absolutePath($normalized);
    }

    private function absolutePath(string $relativePath): string
    {
        return rtrim($this->registryRoot, '/') . '/' . ltrim($relativePath, '/');
    }

    private function toRelativePath(string $absolutePath): string
    {
        $root = rtrim($this->registryRoot, '/') . '/';
        return str_starts_with($absolutePath, $root) ? substr($absolutePath, strlen($root)) : $absolutePath;
    }

    /** @return array<int,string> */
    private function relationFiles(): array
    {
        $directory = $this->registryRoot . '/relations';
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
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
}
