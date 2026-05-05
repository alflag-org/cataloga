<?php

declare(strict_types=1);

namespace Cataloga\Registry;

final class RegistrySettingsRepository
{
    private const DEFAULT_SETTINGS = [
        'version' => 1,
        'tag_keys' => [
            'environment' => ['label' => '環境', 'required' => true, 'values' => ['prod', 'dev', 'sandbox', 'home']],
            'owner' => ['label' => 'オーナー', 'required' => true, 'values' => ['infra-team', 'network-team', 'personal']],
            'site' => ['label' => 'サイト', 'values' => ['kng01']],
            'zone' => ['label' => 'ゾーン', 'values' => ['mgmt', 'client', 'dmz', 'transit']],
            'visibility' => ['label' => '公開範囲', 'values' => ['private', 'internal', 'public']],
            'lifecycle' => ['label' => 'ライフサイクル', 'values' => ['active', 'temporary', 'deprecated', 'retired']],
            'criticality' => ['label' => '重要度', 'values' => ['low', 'medium', 'high', 'critical']],
            'managed-by' => ['label' => '管理方法', 'values' => ['manual', 'ansible', 'terraform', 'cloudflare']],
            'note' => ['label' => '補足', 'free_value' => true],
            'todo' => ['label' => 'TODO', 'free_value' => true],
            'risk' => ['label' => '注意', 'free_value' => true],
        ],
        'reserved_prefixes' => ['cataloga:', 'aws:'],
    ];

    public function __construct(
        private readonly string $registryRoot,
        private readonly RecordParser $recordParser,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function loadSettings(): array
    {
        $settingsPath = rtrim($this->registryRoot, '/') . '/settings.yaml';
        if (!is_file($settingsPath)) {
            return self::DEFAULT_SETTINGS;
        }

        try {
            $parsed = $this->recordParser->parseFile($settingsPath);
        } catch (\Throwable) {
            return self::DEFAULT_SETTINGS;
        }

        return $this->mergeSettings(self::DEFAULT_SETTINGS, $parsed);
    }

    /**
     * @param array<string,mixed> $defaults
     * @param array<string,mixed> $parsed
     * @return array<string,mixed>
     */
    private function mergeSettings(array $defaults, array $parsed): array
    {
        $settings = $defaults;

        if (isset($parsed['version'])) {
            $settings['version'] = (int) $parsed['version'];
        }

        if (is_array($parsed['tag_keys'] ?? null)) {
            foreach ($parsed['tag_keys'] as $key => $row) {
                $tagKey = trim((string) $key);
                if ($tagKey === '') {
                    continue;
                }
                if (!is_array($row)) {
                    continue;
                }

                $base = is_array($settings['tag_keys'][$tagKey] ?? null) ? $settings['tag_keys'][$tagKey] : [];
                $values = is_array($row['values'] ?? null)
                    ? $this->normalizeStringList($row['values'])
                    : (is_array($base['values'] ?? null) ? $base['values'] : []);

                $settings['tag_keys'][$tagKey] = [
                    'label' => trim((string) ($row['label'] ?? ($base['label'] ?? $tagKey))),
                    'required' => (bool) ($row['required'] ?? ($base['required'] ?? false)),
                    'values' => $values,
                    'free_value' => (bool) ($row['free_value'] ?? ($base['free_value'] ?? false)),
                    'allow_empty' => (bool) ($row['allow_empty'] ?? ($base['allow_empty'] ?? false)),
                ];
            }
        }

        if (is_array($parsed['reserved_prefixes'] ?? null)) {
            $settings['reserved_prefixes'] = $this->normalizePrefixList($parsed['reserved_prefixes']);
        }

        return $settings;
    }

    /**
     * @param array<int,mixed> $values
     * @return array<int,string>
     */
    private function normalizeStringList(array $values): array
    {
        $items = [];
        foreach ($values as $value) {
            if (is_scalar($value) || $value === null) {
                $item = trim((string) ($value ?? ''));
                if ($item !== '') {
                    $items[] = $item;
                }
                continue;
            }
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    if (!is_string($k)) {
                        continue;
                    }
                    $item = trim($k);
                    if ($item !== '') {
                        $items[] = $item;
                    }
                }
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @param array<int,mixed> $values
     * @return array<int,string>
     */
    private function normalizePrefixList(array $values): array
    {
        $items = [];
        foreach ($values as $value) {
            if (is_string($value)) {
                $prefix = trim($value);
                if ($prefix !== '') {
                    $items[] = $prefix;
                }
                continue;
            }
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    if (!is_string($k)) {
                        continue;
                    }
                    $prefix = trim($k);
                    if ($prefix !== '') {
                        if (!str_ends_with($prefix, ':')) {
                            $prefix .= ':';
                        }
                        $items[] = $prefix;
                    }
                }
            }
        }

        return array_values(array_unique($items));
    }
}
