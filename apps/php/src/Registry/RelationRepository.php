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
        private readonly ResourceDependencyProjector $dependencyProjector,
    ) {
    }

    public function loadRelationIndex(): array
    {
        $byId = [];
        $idPaths = [];
        $parseErrors = [];

        foreach ($this->dependencyRelationsFromResources() as $relation) {
            $id = (string) ($relation['record']['metadata']['id'] ?? '');
            if ($id === '') {
                continue;
            }
            if (isset($byId[$id])) {
                $id .= '-resource';
                $relation['record']['metadata']['id'] = $id;
            }
            $byId[$id] = $relation;
            $idPaths[$id] = [(string) ($relation['sourcePath'] ?? '')];
        }

        foreach ($this->relationFiles() as $absolutePath) {
            $relativePath = $this->toRelativePath($absolutePath);
            try {
                $raw = $this->recordParser->parseFile($absolutePath);
                $record = $this->canonicalize($raw);
            } catch (\Throwable $exception) {
                $parseErrors[] = ['path' => $relativePath, 'message' => $exception->getMessage()];
                continue;
            }

            $id = (string) (($record['metadata']['id'] ?? ''));
            if ($id === '') {
                $parseErrors[] = ['path' => $relativePath, 'message' => 'relation metadata.id is required.'];
                continue;
            }

            $idPaths[$id] ??= [];
            $idPaths[$id][] = $relativePath;
            $byId[$id] = ['record' => $record, 'sourcePath' => $relativePath];
        }

        return ['byId' => $byId, 'idPaths' => $idPaths, 'parseErrors' => $parseErrors];
    }

    public function listRelations(): array
    {
        $index = $this->loadRelationIndex();
        $relations = [];
        foreach ($index['byId'] as $payload) {
            $record = is_array($payload['record'] ?? null) ? $payload['record'] : [];
            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
            $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
            $relations[] = [
                'id' => (string) ($metadata['id'] ?? ''),
                'type' => (string) ($metadata['type'] ?? ''),
                'from' => (string) ($spec['from'] ?? ''),
                'to' => (string) ($spec['to'] ?? ''),
                'slot' => (string) ($spec['attributes']['slot'] ?? ''),
                'derived' => (bool) ($payload['derived'] ?? false),
                'sourcePath' => (string) ($payload['sourcePath'] ?? ''),
                'record' => $record,
            ];
        }
        usort($relations, static fn(array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']));
        return $relations;
    }

    public function getRelation(string $id): ?array
    {
        $index = $this->loadRelationIndex();
        return $index['byId'][$id] ?? null;
    }

    public function writeRelation(array $record, string $sourcePath): void
    {
        $normalized = $this->pathGuard->normalizeWithinRegistry($sourcePath, 'relations');
        $canonical = $this->canonicalize($record);
        $absolutePath = $this->absolutePath($normalized);
        $directory = dirname($absolutePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Failed to create directory: ' . $directory);
        }

        $content = $this->recordSerializer->encode($canonical, $normalized);
        if (file_put_contents($absolutePath, $content) === false) {
            throw new \RuntimeException('Failed to write relation file: ' . $normalized);
        }
    }

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

    private function canonicalize(array $record): array
    {
        $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
        $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];

        $id = (string) ($metadata['id'] ?? ($record['id'] ?? ''));
        $type = (string) ($metadata['type'] ?? ($record['type'] ?? ''));
        $name = (string) ($metadata['name'] ?? $id);

        $from = (string) ($spec['from'] ?? ($record['source'] ?? ''));
        $to = (string) ($spec['to'] ?? ($record['target'] ?? ''));
        $attributes = is_array($spec['attributes'] ?? null) ? $spec['attributes'] : [];

        return [
            'apiVersion' => 'cataloga.io/v2',
            'kind' => 'Relation',
            'metadata' => ['id' => $id, 'type' => $type, 'name' => $name],
            'spec' => ['from' => $from, 'to' => $to, 'attributes' => $attributes],
        ];
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

    /**
     * @return array<int,array{record:array<string,mixed>,sourcePath:string,derived:bool}>
     */
    private function dependencyRelationsFromResources(): array
    {
        $resources = [];
        foreach ($this->resourceFiles() as $absolutePath) {
            $relativePath = $this->toRelativePath($absolutePath);
            try {
                $record = $this->recordParser->parseFile($absolutePath);
            } catch (\Throwable) {
                continue;
            }
            $resources[] = ['record' => $record, 'sourcePath' => $relativePath];
        }

        return $this->dependencyProjector->project($resources);
    }

    /** @return array<int,string> */
    private function resourceFiles(): array
    {
        $directory = $this->registryRoot . '/resources';
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
