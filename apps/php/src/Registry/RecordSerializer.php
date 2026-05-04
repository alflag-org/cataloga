<?php

declare(strict_types=1);

namespace Cataloga\Registry;

use Symfony\Component\Yaml\Yaml;

final class RecordSerializer
{
    /**
     * @param array<string,mixed> $record
     */
    public function encode(array $record, string $sourcePath): string
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        if ($extension === 'json') {
            $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                throw new \RuntimeException('Failed to serialize record to JSON.');
            }

            return $json . PHP_EOL;
        }

        return Yaml::dump($record, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
}
