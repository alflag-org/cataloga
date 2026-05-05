<?php

declare(strict_types=1);

namespace Cataloga\Registry;

final class DomainPackRepository
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly RecordParser $recordParser,
    ) {
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listDomainPacks(): array
    {
        $root = rtrim($this->projectRoot, '/') . '/domain-packs';
        if (!is_dir($root)) {
            return [];
        }

        $items = [];
        $entries = scandir($root);
        if (!is_array($entries)) {
            return [];
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $packPath = $root . '/' . $entry . '/pack.yaml';
            if (!is_file($packPath)) {
                continue;
            }
            $record = $this->recordParser->parseFile($packPath);
            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
            $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
            $items[] = [
                'id' => (string) ($metadata['id'] ?? $record['id'] ?? $entry),
                'name' => (string) ($metadata['name'] ?? $record['name'] ?? $record['title'] ?? $entry),
                'version' => (string) ($record['version'] ?? ''),
                'description' => (string) ($spec['description'] ?? $record['description'] ?? ''),
                'sourcePath' => 'domain-packs/' . $entry . '/pack.yaml',
                'record' => $record,
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']));
        return $items;
    }
}
