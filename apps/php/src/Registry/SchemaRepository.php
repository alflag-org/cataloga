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
            $requiredTags = is_array($spec['required_tags'] ?? null) ? array_values(array_map('strval', $spec['required_tags'])) : [];
            $recommendedTags = is_array($spec['recommended_tags'] ?? null) ? array_values(array_map('strval', $spec['recommended_tags'])) : [];
            $recommendedManagementTags = is_array($spec['recommended_management_tags'] ?? null) ? array_values(array_map('strval', $spec['recommended_management_tags'])) : [];
            $dependencySlots = $this->normalizeDependencySlots($spec['dependency_slots'] ?? []);
            $sourceTypes = is_array($spec['source_types'] ?? null) ? array_values(array_map('strval', $spec['source_types'])) : [];
            $targetTypes = is_array($spec['target_types'] ?? null) ? array_values(array_map('strval', $spec['target_types'])) : [];
            $items[] = [
                'id' => $id,
                'name' => (string) ($metadata['name'] ?? $spec['name'] ?? $id),
                'description' => (string) ($spec['description'] ?? ''),
                'source' => $source,
                'sourcePath' => $this->toRelativeProjectPath($path),
                'required' => $required,
                'requiredTags' => $requiredTags,
                'recommendedTags' => $recommendedTags,
                'recommendedManagementTags' => $recommendedManagementTags,
                'dependencySlots' => $dependencySlots,
                'sourceTypes' => $sourceTypes,
                'targetTypes' => $targetTypes,
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

    /**
     * @return array<int,array<string,mixed>>
     */
    private function normalizeDependencySlots(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $slots = [];
        foreach ($raw as $slot) {
            if (!is_array($slot)) {
                continue;
            }

            $key = trim((string) ($slot['key'] ?? ''));
            $relationType = trim((string) ($slot['relation_type'] ?? ''));
            if ($key === '' || $relationType === '') {
                continue;
            }

            $direction = trim((string) ($slot['direction'] ?? 'outgoing'));
            if (!in_array($direction, ['outgoing', 'incoming'], true)) {
                $direction = 'outgoing';
            }

            $targetTypes = is_array($slot['target_types'] ?? null) ? array_values(array_map('strval', $slot['target_types'])) : [];
            $sourceTypes = is_array($slot['source_types'] ?? null) ? array_values(array_map('strval', $slot['source_types'])) : [];

            $slots[] = [
                'key' => $key,
                'relation_type' => $relationType,
                'label' => trim((string) ($slot['label'] ?? $key)),
                'description' => trim((string) ($slot['description'] ?? '')),
                'direction' => $direction,
                'target_types' => $targetTypes,
                'source_types' => $sourceTypes,
                'multiple' => (bool) ($slot['multiple'] ?? true),
                'required' => (bool) ($slot['required'] ?? false),
            ];
        }

        return $slots;
    }
}
