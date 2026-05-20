<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Models;

readonly class ClassificationIndustrySearchTerm
{
    public function __construct(
        public string $systemKey,
        public string $versionKey,
        public string $code,
        public string $term,
    ) {
    }
}
