<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Model;

readonly class ClassificationIndustryTranslation
{
    public function __construct(
        public string $system,
        public string $version,
        public string $code,
        public string $locale,
        public string $title,
        public ?string $description,
        public string $sourceFile,
    ) {
    }
}
