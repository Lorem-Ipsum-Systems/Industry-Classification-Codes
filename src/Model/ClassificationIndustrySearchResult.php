<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Model;

readonly class ClassificationIndustrySearchResult
{
    public function __construct(
        public ClassificationIndustryCode $code,
        public float $score,
        public ?string $matchedTerm = null,
    ) {
    }
}
