<?php

declare(strict_types=1);

namespace Cataloga\Registry;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class RecordParser
{
    /**
     * @return array<string,mixed>
     */
    public function parseFile(string $absolutePath): array
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $content = file_get_contents($absolutePath);
        if ($content === false) {
            throw new \RuntimeException('Unable to read record file: ' . $absolutePath);
        }

        if ($extension === 'json') {
            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('Invalid JSON record: ' . $absolutePath);
            }

            return $decoded;
        }

        if (in_array($extension, ['yaml', 'yml'], true)) {
            try {
                $decoded = Yaml::parse($content);
            } catch (ParseException $exception) {
                throw new \RuntimeException('Invalid YAML record: ' . $exception->getMessage());
            }

            if (!is_array($decoded)) {
                throw new \RuntimeException('Invalid YAML record shape: ' . $absolutePath);
            }

            return $decoded;
        }

        throw new \RuntimeException('Unsupported record extension: ' . $absolutePath);
    }
}
