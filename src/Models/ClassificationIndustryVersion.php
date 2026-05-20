<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Models;

readonly class ClassificationIndustryVersion
{
    public function __construct(
        public string $systemKey,
        public string $versionKey,
        public string $name,
    ) {
    }
}
