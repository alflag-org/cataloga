<?php

declare(strict_types=1);

namespace Cataloga\View;

final class ResourceFormViewModel
{
    /**
     * @param array<int,array<string,mixed>> $schemaItems
     * @param array<string,mixed>|null $selectedSchema
     * @param array<int,array<string,mixed>> $basicTags
     * @param array<int,array<string,mixed>> $specFields
     * @param array<int,array{key:string,value:string}> $additionalTags
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $name,
        public readonly string $sourcePath,
        public readonly string $formAction,
        public readonly array $schemaItems,
        public readonly ?array $selectedSchema,
        public readonly array $basicTags,
        public readonly array $specFields,
        public readonly array $additionalTags,
        public readonly string $specJson,
        public readonly bool $isCreateWithoutSchema,
    ) {
    }
}
