<?php

declare(strict_types=1);

namespace Cataloga\Validation;

final class RegistryValidator
{
    private const RECOGNIZED_KINDS = [
        'Entity',
        'Relation',
        'Schema',
        'View',
        'Policy',
        'Evidence',
        'ValidationRule',
        'ChangeSession',
    ];

    /**
     * @param array<string,array{record: array<string,mixed>, sourcePath: string}> $projectedEntities
     * @param array<string,array<int,string>> $finalIdPaths
     * @param array<string,mixed> $registryScan
     * @param array<int,string> $projectionErrors
     * @return array<string,mixed>
     */
    public function validateProjectedState(
        array $projectedEntities,
        array $finalIdPaths,
        array $registryScan,
        array $projectionErrors = []
    ): array {
        $errors = [];
        $warnings = [];

        foreach ($projectionErrors as $message) {
            $errors[] = ['code' => 'projection_error', 'message' => $message];
        }

        foreach ($registryScan['parseErrors'] ?? [] as $parseError) {
            $errors[] = [
                'code' => 'invalid_record',
                'message' => sprintf('%s: %s', (string) ($parseError['path'] ?? ''), (string) ($parseError['message'] ?? '')),
            ];
        }

        foreach ($registryScan['records'] ?? [] as $item) {
            $record = is_array($item['record'] ?? null) ? $item['record'] : [];
            $kind = (string) ($record['kind'] ?? '');

            if ($kind === '') {
                $errors[] = [
                    'code' => 'kind_required',
                    'message' => sprintf('%s: kind is required.', (string) ($item['path'] ?? '')),
                ];
                continue;
            }

            if (!in_array($kind, self::RECOGNIZED_KINDS, true)) {
                $errors[] = [
                    'code' => 'unknown_kind',
                    'message' => sprintf('%s: kind "%s" is not recognized.', (string) ($item['path'] ?? ''), $kind),
                ];
            }
        }

        foreach ($finalIdPaths as $id => $paths) {
            if (count($paths) > 1) {
                $errors[] = [
                    'code' => 'duplicate_entity_id',
                    'message' => sprintf('Duplicate entity id "%s" exists in paths: %s', $id, implode(', ', $paths)),
                ];
            }
        }

        foreach ($projectedEntities as $entity) {
            $record = $entity['record'];
            $path = $entity['sourcePath'];

            $kind = (string) ($record['kind'] ?? '');
            if ($kind !== 'Entity') {
                $errors[] = [
                    'code' => 'entity_kind_invalid',
                    'message' => sprintf('%s must have kind Entity.', $path),
                ];
            }

            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];

            $id = (string) ($metadata['id'] ?? '');
            if ($id === '') {
                $errors[] = [
                    'code' => 'entity_id_required',
                    'message' => sprintf('%s requires metadata.id.', $path),
                ];
            }

            $type = (string) ($metadata['type'] ?? '');
            if ($type === '') {
                $errors[] = [
                    'code' => 'entity_type_required',
                    'message' => sprintf('%s requires metadata.type.', $path),
                ];
            }

            $name = (string) ($metadata['name'] ?? '');
            if ($name === '') {
                $errors[] = [
                    'code' => 'entity_name_required',
                    'message' => sprintf('%s requires metadata.name.', $path),
                ];
            }

            if (!str_starts_with($path, 'entities/')) {
                $errors[] = [
                    'code' => 'entity_path_invalid',
                    'message' => sprintf('Entity path must be under registry/entities: %s', $path),
                ];
            }
        }

        $entityIds = array_keys($projectedEntities);
        foreach ($registryScan['records'] ?? [] as $item) {
            $record = is_array($item['record'] ?? null) ? $item['record'] : [];
            if (($record['kind'] ?? null) !== 'Relation') {
                continue;
            }

            $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
            $fromId = $this->extractRelationEndpoint($spec['from'] ?? null, $spec['source'] ?? null);
            $toId = $this->extractRelationEndpoint($spec['to'] ?? null, $spec['target'] ?? null);

            if ($fromId !== null && !in_array($fromId, $entityIds, true)) {
                $errors[] = [
                    'code' => 'relation_source_missing',
                    'message' => sprintf('%s references missing source entity "%s".', (string) ($item['path'] ?? ''), $fromId),
                ];
            }

            if ($toId !== null && !in_array($toId, $entityIds, true)) {
                $errors[] = [
                    'code' => 'relation_target_missing',
                    'message' => sprintf('%s references missing target entity "%s".', (string) ($item['path'] ?? ''), $toId),
                ];
            }
        }

        return [
            'ranAt' => gmdate(DATE_ATOM),
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function extractRelationEndpoint(mixed $direct, mixed $nested): ?string
    {
        if (is_string($direct) && $direct !== '') {
            return $direct;
        }

        if (is_array($nested)) {
            $id = $nested['id'] ?? null;
            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        return null;
    }
}
