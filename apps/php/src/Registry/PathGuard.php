<?php

declare(strict_types=1);

namespace Cataloga\Registry;

final class PathGuard
{
    public function __construct(private readonly string $registryRoot)
    {
    }

    public function normalizeEntityPath(string $relativePath): string
    {
        return $this->normalizeRecordPath($relativePath, ['resources', 'entities']);
    }

    public function normalizeResourcePath(string $relativePath): string
    {
        return $this->normalizeWithinRegistry($relativePath, 'resources');
    }

    public function normalizeWithinRegistry(string $relativePath, string $expectedTopDirectory): string
    {
        return $this->normalizeRecordPath($relativePath, [$expectedTopDirectory]);
    }

    /**
     * @param array<int,string> $allowedTopDirectories
     */
    private function normalizeRecordPath(string $relativePath, array $allowedTopDirectories): string
    {
        $normalized = str_replace('\\', '/', trim($relativePath));
        $normalized = ltrim($normalized, '/');

        if ($normalized === '') {
            throw new \RuntimeException('Source path is required.');
        }

        if (!preg_match('#\.(yaml|yml|json)$#i', $normalized)) {
            throw new \RuntimeException('Record path extension must be yaml, yml, or json.');
        }

        $parts = explode('/', $normalized);
        $clean = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new \RuntimeException('Path traversal is not allowed.');
            }
            $clean[] = $part;
        }

        if ($clean === []) {
            throw new \RuntimeException('Invalid record path.');
        }

        if (!in_array($clean[0], $allowedTopDirectories, true)) {
            throw new \RuntimeException('Write path must be under registry/' . implode(' or registry/', $allowedTopDirectories) . '.');
        }

        $cleanPath = implode('/', $clean);
        $registryReal = realpath($this->registryRoot);
        if ($registryReal === false) {
            throw new \RuntimeException('Registry root does not exist.');
        }
        $absolute = $registryReal . '/' . $cleanPath;
        if (!str_starts_with($absolute, $registryReal . '/')) {
            throw new \RuntimeException('Write path escapes registry root.');
        }

        $parentDirectory = dirname($absolute);
        $probe = $parentDirectory;
        while (!is_dir($probe)) {
            $next = dirname($probe);
            if ($next === $probe) {
                break;
            }
            $probe = $next;
        }

        $probeReal = realpath($probe);
        if ($probeReal === false || !str_starts_with($probeReal, $registryReal)) {
            throw new \RuntimeException('Write path escapes registry root.');
        }

        return $cleanPath;
    }
}
