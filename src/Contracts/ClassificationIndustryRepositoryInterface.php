<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Contracts;

use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryCode;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustrySearchResult;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustrySystem;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryTranslation;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryVersion;

interface ClassificationIndustryRepositoryInterface
{
    /**
     * @return ClassificationIndustrySystem[]
     */
    public function getSystems(): array;

    /**
     * @return ClassificationIndustryVersion[]
     */
    public function getVersions(string $systemKey): array;

    public function findCode(string $systemKey, string $versionKey, string $code): ?ClassificationIndustryCode;

    /**
     * @return ClassificationIndustryCode[]
     */
    public function getChildren(string $systemKey, string $versionKey, ?string $parentCode = null): array;

    /**
     * @return ClassificationIndustryTranslation[]
     */
    public function getTranslations(string $systemKey, string $versionKey, string $code): array;

    /**
     * @return ClassificationIndustrySearchResult[]
     */
    public function search(string $systemKey, string $versionKey, string $term, int $limit = 10): array;

    public function validateCode(string $systemKey, string $versionKey, string $code): bool;
}
