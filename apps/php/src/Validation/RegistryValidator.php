<?php

declare(strict_types=1);

namespace Cataloga\Validation;

use Cataloga\Registry\DomainPackRepository;
use Cataloga\Registry\RegistrySettingsRepository;
use Cataloga\Registry\SchemaRepository;

final class RegistryValidator
{
    private const RECOGNIZED_KINDS = [
        'Resource',
        'Entity',
        'Relation',
        'Schema',
        'View',
        'Policy',
        'Evidence',
        'ValidationRule',
        'ChangeSession',
    ];

    private const SENSITIVE_TAG_KEYWORDS = [
        'password',
        'secret',
        'token',
        'credential',
        'private-key',
        'api-key',
    ];

    public function __construct(
        private readonly ?SchemaRepository $schemaRepository = null,
        private readonly ?DomainPackRepository $domainPackRepository = null,
        private readonly ?RegistrySettingsRepository $settingsRepository = null,
    ) {
    }

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

        $settings = $this->settingsRepository?->loadSettings() ?? [
            'tag_keys' => [],
            'reserved_prefixes' => ['cataloga:'],
        ];
        $tagSettings = is_array($settings['tag_keys'] ?? null) ? $settings['tag_keys'] : [];
        $reservedPrefixes = is_array($settings['reserved_prefixes'] ?? null) ? $settings['reserved_prefixes'] : ['cataloga:'];
        $schemasById = $this->activeSchemasById();

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
            $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];

            if (!in_array((string) ($record['kind'] ?? ''), ['Resource', 'Entity'], true)) {
                $errors[] = ['code' => 'entity_kind_invalid', 'message' => sprintf('%s must have kind Resource.', $path)];
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
            if (!str_starts_with($path, 'resources/') && !str_starts_with($path, 'entities/')) {
                $errors[] = ['code' => 'entity_path_invalid', 'message' => sprintf('Resource path must be under registry/resources: %s', $path)];
            }
            if (str_starts_with($path, 'entities/')) {
                $warnings[] = ['code' => 'legacy_entity_path', 'message' => sprintf('%s uses legacy registry/entities path. Save the resource to migrate it under registry/resources.', $path)];
            }

            $type = (string) ($metadata['type'] ?? '');
            $schema = $schemasById[$type] ?? null;
            $tags = $this->normalizedTagsForValidation($metadata['tags'] ?? []);

            if (is_array($metadata['tags'] ?? null) && !$this->isAssocArray((array) $metadata['tags'])) {
                $warnings[] = [
                    'code' => 'legacy_tag_list',
                    'message' => sprintf('%s uses legacy list-style metadata.tags. Save the resource to normalize into key-value tags.', $path),
                ];
            }

            foreach ($tags as $tagKey => $tagValue) {
                if ($tagKey === '') {
                    $errors[] = ['code' => 'tag_key_invalid', 'message' => sprintf('%s has an empty tag key.', $path)];
                    continue;
                }
                if (!is_string($tagValue)) {
                    $errors[] = ['code' => 'tag_value_invalid', 'message' => sprintf('%s tag "%s" must be a string.', $path, $tagKey)];
                    continue;
                }

                foreach ($reservedPrefixes as $prefix) {
                    if (!is_string($prefix) || $prefix === '') {
                        continue;
                    }
                    if (str_starts_with($tagKey, $prefix)) {
                        $errors[] = ['code' => 'reserved_tag_prefix', 'message' => sprintf('%s tag "%s" uses reserved prefix "%s".', $path, $tagKey, $prefix)];
                    }
                }

                $normalizedKey = strtolower($tagKey);
                foreach (self::SENSITIVE_TAG_KEYWORDS as $keyword) {
                    if (str_contains($normalizedKey, $keyword)) {
                        $errors[] = ['code' => 'sensitive_tag_key', 'message' => sprintf('%s tag "%s" looks sensitive and is not allowed.', $path, $tagKey)];
                        break;
                    }
                }
            }

            if ($schema !== null) {
                $requiredTags = is_array($schema['requiredTags'] ?? null) ? $schema['requiredTags'] : [];
                foreach ($requiredTags as $requiredTag) {
                    $requiredTag = (string) $requiredTag;
                    if ($requiredTag === '') {
                        continue;
                    }
                    $allowEmpty = (bool) ((is_array($tagSettings[$requiredTag] ?? null) ? $tagSettings[$requiredTag]['allow_empty'] ?? false : false));
                    $value = (string) ($tags[$requiredTag] ?? '');
                    if ($value === '' && !$allowEmpty) {
                        $errors[] = ['code' => 'required_tag_missing', 'message' => sprintf('%s requires tag "%s" with non-empty value.', $path, $requiredTag)];
                    }
                }

                $recommendedTags = is_array($schema['recommendedTags'] ?? null) ? $schema['recommendedTags'] : [];
                foreach ($recommendedTags as $recommendedTag) {
                    $recommendedTag = (string) $recommendedTag;
                    if ($recommendedTag === '') {
                        continue;
                    }
                    if (!array_key_exists($recommendedTag, $tags) || (string) $tags[$recommendedTag] === '') {
                        $warnings[] = ['code' => 'recommended_tag_missing', 'message' => sprintf('%s recommends tag "%s".', $path, $recommendedTag)];
                    }
                }
            }

            foreach (['environment', 'owner'] as $legacySpecKey) {
                if (isset($spec[$legacySpecKey]) && !array_key_exists($legacySpecKey, $tags) && trim((string) $spec[$legacySpecKey]) !== '') {
                    $warnings[] = [
                        'code' => 'legacy_spec_metadata',
                        'message' => sprintf('%s uses spec.%s. Move it to metadata.tags.%s.', $path, $legacySpecKey, $legacySpecKey),
                    ];
                }
            }
        }

        $entityIds = array_keys($projectedEntities);
        $slotUsage = [];
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
            $derivedFromResource = str_starts_with($path, 'resources/');
            if (!str_starts_with($path, 'relations/') && !$derivedFromResource) {
                $errors[] = ['code' => 'relation_path_invalid', 'message' => sprintf('Relation path must be under registry/relations: %s', $path)];
            }

            if ($from === '' || $to === '' || !isset($projectedEntities[$from]) || !isset($projectedEntities[$to])) {
                continue;
            }

            $relationType = (string) ($metadata['type'] ?? '');
            $fromType = (string) (($projectedEntities[$from]['record']['metadata']['type'] ?? ''));
            $toType = (string) (($projectedEntities[$to]['record']['metadata']['type'] ?? ''));

            $fromSchema = $schemasById[$fromType] ?? null;
            $toSchema = $schemasById[$toType] ?? null;

            $matchesSourceSlot = false;
            $matchesTargetSlot = false;

            $fromSlots = is_array($fromSchema['dependencySlots'] ?? null) ? $fromSchema['dependencySlots'] : [];
            foreach ($fromSlots as $slot) {
                if ((string) ($slot['direction'] ?? 'outgoing') !== 'outgoing') {
                    continue;
                }
                if ((string) ($slot['relation_type'] ?? '') !== $relationType && (string) ($slot['key'] ?? '') !== $relationType) {
                    continue;
                }

                $slotKey = (string) ($slot['key'] ?? '');
                $targetTypes = is_array($slot['target_types'] ?? null) ? $slot['target_types'] : [];
                if ($targetTypes !== [] && !in_array($toType, $targetTypes, true)) {
                    $errors[] = [
                        'code' => 'dependency_slot_target_type_mismatch',
                        'message' => sprintf('%s relation "%s" target type "%s" is not compatible with slot "%s" on source "%s".', $path, $relationType, $toType, $slotKey, $from),
                    ];
                    continue;
                }

                $matchesSourceSlot = true;
                $slotUsage[$from][$slotKey] = (int) ($slotUsage[$from][$slotKey] ?? 0) + 1;
            }

            $toSlots = is_array($toSchema['dependencySlots'] ?? null) ? $toSchema['dependencySlots'] : [];
            foreach ($toSlots as $slot) {
                if ((string) ($slot['direction'] ?? 'outgoing') !== 'incoming') {
                    continue;
                }
                if ((string) ($slot['relation_type'] ?? '') !== $relationType && (string) ($slot['key'] ?? '') !== $relationType) {
                    continue;
                }

                $slotKey = (string) ($slot['key'] ?? '');
                $sourceTypes = is_array($slot['source_types'] ?? null) ? $slot['source_types'] : [];
                if ($sourceTypes !== [] && !in_array($fromType, $sourceTypes, true)) {
                    $errors[] = [
                        'code' => 'dependency_slot_source_type_mismatch',
                        'message' => sprintf('%s relation "%s" source type "%s" is not compatible with incoming slot "%s" on target "%s".', $path, $relationType, $fromType, $slotKey, $to),
                    ];
                    continue;
                }

                $matchesTargetSlot = true;
                $slotUsage[$to][$slotKey] = (int) ($slotUsage[$to][$slotKey] ?? 0) + 1;
            }

            if (($fromSlots !== [] || $toSlots !== []) && !$matchesSourceSlot && !$matchesTargetSlot) {
                $errors[] = [
                    'code' => 'dependency_slot_no_match',
                    'message' => sprintf('%s relation "%s" does not match declared dependency slots for source "%s" or target "%s".', $path, $relationType, $from, $to),
                ];
            }
        }

        foreach ($projectedEntities as $entityId => $entity) {
            $record = is_array($entity['record'] ?? null) ? $entity['record'] : [];
            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
            $type = (string) ($metadata['type'] ?? '');
            $schema = $schemasById[$type] ?? null;
            if ($schema === null) {
                continue;
            }

            $slots = is_array($schema['dependencySlots'] ?? null) ? $schema['dependencySlots'] : [];
            foreach ($slots as $slot) {
                $slotKey = (string) ($slot['key'] ?? '');
                if ($slotKey === '') {
                    continue;
                }

                $count = (int) ($slotUsage[$entityId][$slotKey] ?? 0);
                if (!(bool) ($slot['multiple'] ?? true) && $count > 1) {
                    $errors[] = [
                        'code' => 'dependency_slot_multiple_violation',
                        'message' => sprintf('Entity "%s" slot "%s" allows only one dependency.', $entityId, $slotKey),
                    ];
                }

                if ((bool) ($slot['required'] ?? false) && $count === 0) {
                    $errors[] = [
                        'code' => 'dependency_slot_required_missing',
                        'message' => sprintf('Entity "%s" requires dependency slot "%s".', $entityId, $slotKey),
                    ];
                }
            }
        }

        return ['ranAt' => gmdate(DATE_ATOM), 'valid' => count($errors) === 0, 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function activeSchemasById(): array
    {
        if ($this->schemaRepository === null) {
            return [];
        }

        $schemas = $this->schemaRepository->listSchemas();
        $packState = [];
        if ($this->domainPackRepository !== null) {
            foreach ($this->domainPackRepository->listDomainPacks() as $pack) {
                $packId = (string) ($pack['id'] ?? '');
                if ($packId === '') {
                    continue;
                }
                $packState[$packId] = (bool) (($pack['installed'] ?? false) && ($pack['enabled'] ?? false));
            }
        }

        $items = [];
        foreach ($schemas as $schema) {
            $source = (string) ($schema['source'] ?? '');
            $sourcePath = (string) ($schema['sourcePath'] ?? '');
            if ($source === 'domain-pack') {
                if (preg_match('#^domain-packs/([^/]+)/#', $sourcePath, $matches) !== 1) {
                    continue;
                }
                $packId = (string) ($matches[1] ?? '');
                if (($packState[$packId] ?? false) !== true) {
                    continue;
                }
            }

            $id = (string) ($schema['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $items[$id] = $schema;
        }

        return $items;
    }

    /**
     * @return array<string,string>
     */
    private function normalizedTagsForValidation(mixed $rawTags): array
    {
        $tags = [];
        if (!is_array($rawTags)) {
            return $tags;
        }

        foreach ($rawTags as $key => $value) {
            if (is_int($key)) {
                $legacy = trim((string) $value);
                if ($legacy === '') {
                    continue;
                }

                if (str_contains($legacy, ':')) {
                    [$legacyKey, $legacyValue] = explode(':', $legacy, 2);
                    $legacyKey = trim($legacyKey);
                    if ($legacyKey === '') {
                        continue;
                    }
                    $tags[$legacyKey] = trim($legacyValue);
                    continue;
                }

                $tags[$legacy] = '';
                continue;
            }

            $tagKey = trim((string) $key);
            if ($tagKey === '') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $tags[$tagKey] = trim((string) ($value ?? ''));
                continue;
            }

            $tags[$tagKey] = '';
        }

        return $tags;
    }

    private function isAssocArray(array $value): bool
    {
        if ($value === []) {
            return false;
        }
        $keys = array_keys($value);

        return $keys !== range(0, count($keys) - 1);
    }
}
