<?php

declare(strict_types=1);

namespace Cataloga\Registry;

use Symfony\Component\Yaml\Yaml;

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
        $catalog = $this->loadCatalog();
        $lock = $this->loadLock();
        $states = is_array($lock['packs'] ?? null) ? $lock['packs'] : [];

        $items = [];
        foreach ($catalog as $id => $pack) {
            $state = is_array($states[$id] ?? null) ? $states[$id] : null;
            $installed = $state !== null ? (bool) ($state['installed'] ?? false) : false;
            $enabled = $state !== null ? (bool) ($state['enabled'] ?? false) : false;

            $items[] = [
                'id' => $id,
                'name' => (string) ($pack['name'] ?? $id),
                'version' => (string) ($pack['version'] ?? ''),
                'description' => (string) ($pack['description'] ?? ''),
                'sourcePath' => (string) ($pack['sourcePath'] ?? ''),
                'installed' => $installed,
                'enabled' => $enabled,
                'status' => $installed ? ($enabled ? 'Enabled' : 'Disabled') : 'Available',
                'resourceTypes' => $pack['resourceTypes'] ?? [],
                'dependencyTypes' => $pack['dependencyTypes'] ?? [],
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']));

        return $items;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listInstalledPacks(): array
    {
        return array_values(array_filter(
            $this->listDomainPacks(),
            static fn (array $pack): bool => (bool) ($pack['installed'] ?? false)
        ));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listAvailablePacks(): array
    {
        return array_values(array_filter(
            $this->listDomainPacks(),
            static fn (array $pack): bool => !((bool) ($pack['installed'] ?? false))
        ));
    }

    /**
     * @return array<string,mixed>
     */
    public function installPack(string $name): array
    {
        return $this->setPackState($name, true, true);
    }

    /**
     * @return array<string,mixed>
     */
    public function enablePack(string $name): array
    {
        $lock = $this->loadLock();
        $packs = is_array($lock['packs'] ?? null) ? $lock['packs'] : [];
        $state = is_array($packs[$name] ?? null) ? $packs[$name] : null;
        if ($state === null || !($state['installed'] ?? false)) {
            throw new \RuntimeException('Type pack is not installed: ' . $name);
        }

        return $this->setPackState($name, true, true);
    }

    /**
     * @return array<string,mixed>
     */
    public function disablePack(string $name): array
    {
        $impact = $this->packImpact($name);
        if (($impact['installed'] ?? false) !== true) {
            throw new \RuntimeException('Type pack is not installed: ' . $name);
        }

        $result = $this->setPackState($name, true, false);
        $result['impact'] = $impact;

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    public function uninstallPack(string $name): array
    {
        $impact = $this->packImpact($name);
        $result = $this->setPackState($name, false, false);
        $result['impact'] = $impact;

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    public function packImpact(string $name): array
    {
        $catalog = $this->loadCatalog();
        if (!isset($catalog[$name])) {
            throw new \RuntimeException('Type pack not found: ' . $name);
        }

        $lock = $this->loadLock();
        $states = is_array($lock['packs'] ?? null) ? $lock['packs'] : [];
        $state = is_array($states[$name] ?? null) ? $states[$name] : null;

        $resourceTypes = [];
        foreach (($catalog[$name]['resourceTypes'] ?? []) as $row) {
            $resourceTypes[] = (string) ($row['name'] ?? '');
        }
        $resourceTypes = array_values(array_filter(array_unique($resourceTypes)));

        $dependencyTypes = [];
        foreach (($catalog[$name]['dependencyTypes'] ?? []) as $row) {
            $dependencyTypes[] = (string) ($row['name'] ?? '');
        }
        $dependencyTypes = array_values(array_filter(array_unique($dependencyTypes)));

        $entityRepo = new EntityRepository(
            rtrim($this->projectRoot, '/') . '/registry',
            $this->recordParser,
            new RecordSerializer(),
            new PathGuard(rtrim($this->projectRoot, '/') . '/registry'),
        );
        $relationRepo = new RelationRepository(
            rtrim($this->projectRoot, '/') . '/registry',
            $this->recordParser,
            new RecordSerializer(),
            new PathGuard(rtrim($this->projectRoot, '/') . '/registry'),
        );

        $resourcesByType = [];
        foreach ($entityRepo->listEntities() as $entity) {
            $type = (string) ($entity['type'] ?? '');
            if ($type === '' || !in_array($type, $resourceTypes, true)) {
                continue;
            }
            $resourcesByType[$type] = (int) ($resourcesByType[$type] ?? 0) + 1;
        }

        $dependenciesByType = [];
        foreach ($relationRepo->listRelations() as $relation) {
            $type = (string) ($relation['type'] ?? '');
            if ($type === '' || !in_array($type, $dependencyTypes, true)) {
                continue;
            }
            $dependenciesByType[$type] = (int) ($dependenciesByType[$type] ?? 0) + 1;
        }

        ksort($resourcesByType);
        ksort($dependenciesByType);

        return [
            'id' => $name,
            'installed' => (bool) ($state['installed'] ?? false),
            'enabled' => (bool) ($state['enabled'] ?? false),
            'resourceTypes' => $resourceTypes,
            'dependencyTypes' => $dependencyTypes,
            'affectedResources' => $resourcesByType,
            'affectedDependencies' => $dependenciesByType,
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function loadCatalog(): array
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

            $packRoot = $root . '/' . $entry;
            $packPath = $packRoot . '/pack.yaml';
            if (!is_file($packPath)) {
                continue;
            }

            $record = $this->recordParser->parseFile($packPath);
            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
            $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];

            $id = (string) ($metadata['id'] ?? $record['id'] ?? $entry);
            if ($id === '') {
                $id = $entry;
            }

            $schemas = $this->loadSchemasForPack($entry);

            $items[$id] = [
                'id' => $id,
                'name' => (string) ($metadata['name'] ?? $record['name'] ?? $record['title'] ?? $id),
                'version' => (string) ($record['version'] ?? ''),
                'description' => (string) ($spec['description'] ?? $record['description'] ?? ''),
                'sourcePath' => 'domain-packs/' . $entry . '/pack.yaml',
                'resourceTypes' => $schemas['resourceTypes'],
                'dependencyTypes' => $schemas['dependencyTypes'],
            ];
        }

        return $items;
    }

    /**
     * @return array{resourceTypes:array<int,array<string,string>>,dependencyTypes:array<int,array<string,string>>}
     */
    private function loadSchemasForPack(string $directoryName): array
    {
        $root = rtrim($this->projectRoot, '/') . '/domain-packs/' . $directoryName . '/schemas';
        if (!is_dir($root)) {
            return ['resourceTypes' => [], 'dependencyTypes' => []];
        }

        $resourceTypes = [];
        $dependencyTypes = [];

        foreach (glob($root . '/*.{yaml,yml}', GLOB_BRACE) ?: [] as $schemaPath) {
            try {
                $record = $this->recordParser->parseFile($schemaPath);
            } catch (\Throwable) {
                continue;
            }

            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
            $spec = is_array($record['spec'] ?? null) ? $record['spec'] : [];
            $name = (string) ($metadata['id'] ?? '');
            if ($name === '') {
                continue;
            }

            $label = (string) ($metadata['name'] ?? $name);
            $kind = (string) ($spec['kind'] ?? 'entity');

            if ($kind === 'relation') {
                $dependencyTypes[] = ['name' => $name, 'label' => $label];
                continue;
            }

            $resourceTypes[] = ['name' => $name, 'label' => $label];
        }

        usort($resourceTypes, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));
        usort($dependencyTypes, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        return ['resourceTypes' => $resourceTypes, 'dependencyTypes' => $dependencyTypes];
    }

    /**
     * @return array<string,mixed>
     */
    private function loadLock(): array
    {
        $path = $this->lockPath();
        if (!is_file($path)) {
            $catalog = $this->loadCatalog();
            $packs = [];
            foreach ($catalog as $id => $_pack) {
                $packs[$id] = ['installed' => true, 'enabled' => true];
            }
            $lock = ['version' => 1, 'packs' => $packs];
            $this->saveLock($lock);

            return $lock;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Failed to read type pack lock file.');
        }

        $decoded = Yaml::parse($raw);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Type pack lock file is invalid.');
        }

        if (!isset($decoded['packs']) || !is_array($decoded['packs'])) {
            $decoded['packs'] = [];
        }

        return $decoded;
    }

    /**
     * @param array<string,mixed> $lock
     */
    private function saveLock(array $lock): void
    {
        $path = $this->lockPath();
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Failed to create directory for type pack lock file: ' . $directory);
        }

        $encoded = Yaml::dump($lock, 4, 2);
        if (file_put_contents($path, $encoded) === false) {
            throw new \RuntimeException('Failed to write type pack lock file.');
        }
    }

    private function lockPath(): string
    {
        return rtrim($this->projectRoot, '/') . '/registry/type-packs.lock.yaml';
    }

    /**
     * @return array<string,mixed>
     */
    private function setPackState(string $name, bool $installed, bool $enabled): array
    {
        $catalog = $this->loadCatalog();
        if (!isset($catalog[$name])) {
            throw new \RuntimeException('Type pack not found: ' . $name);
        }

        $lock = $this->loadLock();
        $lock['version'] = 1;
        if (!isset($lock['packs']) || !is_array($lock['packs'])) {
            $lock['packs'] = [];
        }

        $lock['packs'][$name] = ['installed' => $installed, 'enabled' => $installed && $enabled];
        $this->saveLock($lock);

        $items = $this->listDomainPacks();
        foreach ($items as $item) {
            if (($item['id'] ?? '') === $name) {
                return $item;
            }
        }

        throw new \RuntimeException('Type pack state update failed: ' . $name);
    }
}
