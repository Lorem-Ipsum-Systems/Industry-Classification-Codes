<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Model;

readonly class ClassificationIndustrySystem
{
    public function __construct(
        public string $key,
        public string $name,
        public string $region,
        public string $description,
    ) {
    }
}
