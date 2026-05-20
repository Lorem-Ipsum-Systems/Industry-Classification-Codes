<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Model;

readonly class ClassificationIndustryCode
{
    /**
     * @param string[] $aliases
     * @param string[] $sourceFiles
     */
    public function __construct(
        public string $system,
        public string $version,
        public string $code,
        public string $normalizedCode,
        public array $aliases,
        public string $title,
        public ?string $description,
        public mixed $level,
        public string $levelName,
        public int $depth,
        public ?string $parentCode,
        public string $codeType,
        public bool $isLeaf,
        public bool $isSelectable,
        public bool $isActive,
        public ?string $variant,
        public ?string $changeIndicator,
        public ?string $introductoryText,
        public ?string $includes,
        public ?string $includesAlso,
        public ?string $excludes,
        public ?string $implementationRule,
        public array $sourceFiles
    ) {
    }
}
