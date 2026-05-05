<?php

declare(strict_types=1);

namespace Cataloga\Registry;

final class SchemaRepository
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $registryRoot,
        private readonly RecordParser $recordParser,
    ) {
    }

    /** @return array<int,array<string,mixed>> */
    public function listSchemas(): array
    {
        $items = [];
        foreach ($this->schemaFiles() as $row) {
            [$path, $source] = $row;
            try { $record = $this->recordParser->parseFile($path); } catch (\Throwable) { continue; }
            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
            $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
            $id = (string) ($metadata['id'] ?? $spec['id'] ?? '');
            if ($id === '') { continue; }
            $properties = is_array($spec['properties'] ?? null) ? $spec['properties'] : [];
            $required = is_array($spec['required'] ?? null) ? array_values(array_map('strval', $spec['required'])) : [];
            $items[] = [
                'id' => $id,
                'name' => (string) ($metadata['name'] ?? $spec['name'] ?? $id),
                'description' => (string) ($spec['description'] ?? ''),
                'source' => $source,
                'sourcePath' => $this->toRelativeProjectPath($path),
                'required' => $required,
                'properties' => $properties,
                'kind' => (string) ($spec['kind'] ?? 'entity'),
                'record' => $record,
            ];
        }
        usort($items, static fn(array $a, array $b): int => strcmp((string)$a['id'], (string)$b['id']));
        return $items;
    }

    private function toRelativeProjectPath(string $abs): string
    {
        $root = rtrim($this->projectRoot, '/') . '/';
        return str_starts_with($abs, $root) ? substr($abs, strlen($root)) : $abs;
    }

    /** @return array<int,array{0:string,1:string}> */
    private function schemaFiles(): array
    {
        $files = [];
        $registryDir = rtrim($this->registryRoot, '/') . '/schemas';
        if (is_dir($registryDir)) {
            foreach (glob($registryDir . '/*.{yaml,yml}', GLOB_BRACE) ?: [] as $f) { $files[] = [$f, 'registry']; }
        }
        $packsRoot = rtrim($this->projectRoot, '/') . '/domain-packs';
        if (is_dir($packsRoot)) {
            foreach (glob($packsRoot . '/*/schemas/*.{yaml,yml}', GLOB_BRACE) ?: [] as $f) { $files[] = [$f, 'domain-pack']; }
        }
        return $files;
    }
}
