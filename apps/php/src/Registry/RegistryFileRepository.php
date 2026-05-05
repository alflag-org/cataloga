<?php

declare(strict_types=1);

namespace Cataloga\Registry;

final class RegistryFileRepository
{
    public function __construct(
        private readonly string $registryRoot,
    ) {
    }

    public function read(string $relativePath): ?string
    {
        $absolute = $this->absolutePath($relativePath);
        if (!is_file($absolute)) {
            return null;
        }

        $content = file_get_contents($absolute);

        return $content === false ? null : $content;
    }

    public function write(string $relativePath, string $content): void
    {
        $absolute = $this->absolutePath($relativePath);
        $directory = dirname($absolute);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Failed to create directory: ' . $directory);
        }
        if (file_put_contents($absolute, $content) === false) {
            throw new \RuntimeException('Failed to write registry file: ' . $relativePath);
        }
    }

    private function absolutePath(string $relativePath): string
    {
        return rtrim($this->registryRoot, '/') . '/' . ltrim($relativePath, '/');
    }
}

