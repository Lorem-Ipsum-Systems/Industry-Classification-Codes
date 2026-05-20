<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Models;

readonly class ClassificationIndustryCode
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $systemKey,
        public string $versionKey,
        public string $code,
        public string $name,
        public ?string $parentCode = null,
        public ?string $description = null,
        public array $metadata = [],
    ) {
    }
}
