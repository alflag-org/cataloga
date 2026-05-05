<?php

declare(strict_types=1);

namespace Cataloga\Registry;

final class ResourceDependencyProjector
{
    /**
     * @param array<int,array{record:array<string,mixed>,sourcePath:string}> $resources
     * @return array<int,array{record:array<string,mixed>,sourcePath:string,derived:bool}>
     */
    public function project(array $resources): array
    {
        $relations = [];
        foreach ($resources as $resource) {
            $record = is_array($resource['record'] ?? null) ? $resource['record'] : [];
            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
            $from = trim((string) ($metadata['id'] ?? ''));
            if ($from === '') {
                continue;
            }

            $dependencies = is_array($record['dependencies'] ?? null) ? $record['dependencies'] : [];
            foreach ($dependencies as $slot => $targets) {
                $slotKey = trim((string) $slot);
                if ($slotKey === '') {
                    continue;
                }

                $targetList = is_array($targets) ? $targets : [$targets];
                foreach ($targetList as $target) {
                    if (!is_scalar($target) && $target !== null) {
                        continue;
                    }

                    $to = trim((string) ($target ?? ''));
                    if ($to === '') {
                        continue;
                    }

                    $relations[] = [
                        'record' => [
                            'apiVersion' => 'cataloga.io/v2',
                            'kind' => 'Relation',
                            'metadata' => [
                                'id' => $this->relationId($from, $slotKey, $to),
                                'type' => $slotKey,
                                'name' => $from . ' ' . $slotKey . ' ' . $to,
                            ],
                            'spec' => [
                                'from' => $from,
                                'to' => $to,
                                'attributes' => [
                                    'slot' => $slotKey,
                                    'derived_from' => 'resource.dependencies',
                                ],
                            ],
                        ],
                        'sourcePath' => (string) ($resource['sourcePath'] ?? ''),
                        'derived' => true,
                    ];
                }
            }
        }

        return $relations;
    }

    private function relationId(string $from, string $slot, string $to): string
    {
        $slug = strtolower($from . '-' . $slot . '-' . $to);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : bin2hex(random_bytes(4));
    }
}

