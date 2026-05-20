<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Model;

readonly class ClassificationIndustrySearchTerm
{
    public function __construct(
        public string $system,
        public string $version,
        public string $code,
        public string $term,
        public string $source,
        public string $sourceFile,
    ) {
    }
}
