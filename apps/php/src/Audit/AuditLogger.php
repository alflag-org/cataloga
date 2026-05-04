<?php

declare(strict_types=1);

namespace Cataloga\Audit;

final class AuditLogger
{
    private readonly string $auditPath;

    public function __construct(private readonly string $runtimeRoot)
    {
        $this->auditPath = rtrim($this->runtimeRoot, '/') . '/audit.log';
    }

    /**
     * @param array<string,mixed> $entry
     */
    public function append(array $entry): void
    {
        $directory = dirname($this->auditPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create audit directory: ' . $directory);
        }

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            throw new \RuntimeException('Unable to encode audit entry.');
        }

        if (file_put_contents($this->auditPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write audit log.');
        }
    }
}
