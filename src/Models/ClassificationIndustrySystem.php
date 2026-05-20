<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Models;

readonly class ClassificationIndustrySystem
{
    public function __construct(
        public string $key,
        public string $name,
    ) {
    }
}
