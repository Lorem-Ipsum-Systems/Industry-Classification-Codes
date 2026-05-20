<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Model;

readonly class ClassificationIndustryVersion
{
    public function __construct(
        public string $system,
        public string $version,
        public string $label,
        public bool $isLatest,
        public string $dataPath,
    ) {
    }
}
