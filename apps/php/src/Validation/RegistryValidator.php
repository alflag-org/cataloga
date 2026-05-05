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
     * @param array<string,array<int,string>> $finalEntityPaths
     * @param array<string,array{record: array<string,mixed>, sourcePath: string}> $projectedRelations
     * @param array<string,array<int,string>> $finalRelationPaths
     * @param array<string,mixed> $registryScan
     * @param array<int,string> $projectionErrors
     * @return array<string,mixed>
     */
    public function validateProjectedState(
        array $projectedEntities,
        array $finalEntityPaths,
        array $projectedRelations,
        array $finalRelationPaths,
        array $registryScan,
        array $projectionErrors = []
    ): array {
        $errors = [];
        $warnings = [];

        foreach ($projectionErrors as $message) {
            $errors[] = ['code' => 'projection_error', 'message' => $message];
        }

        foreach (($registryScan['parseErrors'] ?? []) as $parseError) {
            $errors[] = [
                'code' => 'invalid_record',
                'message' => sprintf('%s: %s', (string) ($parseError['path'] ?? ''), (string) ($parseError['message'] ?? '')),
            ];
        }

        foreach (($registryScan['records'] ?? []) as $item) {
            $record = is_array($item['record'] ?? null) ? $item['record'] : [];
            $kind = (string) ($record['kind'] ?? '');
            if ($kind === '') {
                $errors[] = ['code' => 'kind_required', 'message' => sprintf('%s: kind is required.', (string) ($item['path'] ?? ''))];
                continue;
            }
            if (!in_array($kind, self::RECOGNIZED_KINDS, true)) {
                $errors[] = ['code' => 'unknown_kind', 'message' => sprintf('%s: kind "%s" is not recognized.', (string) ($item['path'] ?? ''), $kind)];
            }
        }

        foreach ($finalEntityPaths as $id => $paths) {
            if (count($paths) > 1) {
                $errors[] = ['code' => 'duplicate_entity_id', 'message' => sprintf('Duplicate entity id "%s" exists in paths: %s', $id, implode(', ', $paths))];
            }
        }

        foreach ($finalRelationPaths as $id => $paths) {
            if (count($paths) > 1) {
                $errors[] = ['code' => 'duplicate_relation_id', 'message' => sprintf('Duplicate relation id "%s" exists in paths: %s', $id, implode(', ', $paths))];
            }
        }

        foreach ($projectedEntities as $entity) {
            $record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
            $path = (string) ($entity['sourcePath'] ?? '');
            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];

            if ((string) ($record['kind'] ?? '') !== 'Entity') {
                $errors[] = ['code' => 'entity_kind_invalid', 'message' => sprintf('%s must have kind Entity.', $path)];
            }
            if ((string) ($metadata['id'] ?? '') === '') {
                $errors[] = ['code' => 'entity_id_required', 'message' => sprintf('%s requires metadata.id.', $path)];
            }
            if ((string) ($metadata['type'] ?? '') === '') {
                $errors[] = ['code' => 'entity_type_required', 'message' => sprintf('%s requires metadata.type.', $path)];
            }
            if ((string) ($metadata['name'] ?? '') === '') {
                $errors[] = ['code' => 'entity_name_required', 'message' => sprintf('%s requires metadata.name.', $path)];
            }
            if (!str_starts_with($path, 'entities/')) {
                $errors[] = ['code' => 'entity_path_invalid', 'message' => sprintf('Entity path must be under registry/entities: %s', $path)];
            }
        }

        $entityIds = array_keys($projectedEntities);
        foreach ($projectedRelations as $relation) {
            $record = is_array($relation['record'] ?? null) ? $relation['record'] : [];
            $path = (string) ($relation['sourcePath'] ?? '');
            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
            $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];

            if ((string) ($record['kind'] ?? '') !== 'Relation') {
                $errors[] = ['code' => 'relation_kind_invalid', 'message' => sprintf('%s must have kind Relation.', $path)];
            }
            if ((string) ($metadata['id'] ?? '') === '') {
                $errors[] = ['code' => 'relation_id_required', 'message' => sprintf('%s requires metadata.id.', $path)];
            }
            if ((string) ($metadata['type'] ?? '') === '') {
                $errors[] = ['code' => 'relation_type_required', 'message' => sprintf('%s requires metadata.type.', $path)];
            }

            $from = (string) ($spec['from'] ?? '');
            $to = (string) ($spec['to'] ?? '');

            if ($from === '') {
                $errors[] = ['code' => 'relation_from_required', 'message' => sprintf('%s requires spec.from.', $path)];
            }
            if ($to === '') {
                $errors[] = ['code' => 'relation_to_required', 'message' => sprintf('%s requires spec.to.', $path)];
            }
            if ($from !== '' && !in_array($from, $entityIds, true)) {
                $errors[] = ['code' => 'relation_source_missing', 'message' => sprintf('%s references missing source entity "%s".', $path, $from)];
            }
            if ($to !== '' && !in_array($to, $entityIds, true)) {
                $errors[] = ['code' => 'relation_target_missing', 'message' => sprintf('%s references missing target entity "%s".', $path, $to)];
            }
            if (!str_starts_with($path, 'relations/')) {
                $errors[] = ['code' => 'relation_path_invalid', 'message' => sprintf('Relation path must be under registry/relations: %s', $path)];
            }
        }

        return ['ranAt' => gmdate(DATE_ATOM), 'valid' => count($errors) === 0, 'errors' => $errors, 'warnings' => $warnings];
    }
}
