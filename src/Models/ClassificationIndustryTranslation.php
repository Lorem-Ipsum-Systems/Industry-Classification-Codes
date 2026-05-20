<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Models;

readonly class ClassificationIndustryTranslation
{
    public function __construct(
        public string $systemKey,
        public string $versionKey,
        public string $code,
        public string $language,
        public string $name,
        public ?string $description = null,
    ) {
    }
}
