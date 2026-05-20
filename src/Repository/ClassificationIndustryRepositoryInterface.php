<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Repository;

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

    public function isValidCode(string $systemKey, string $versionKey, string $code): bool;

    /**
     * @return ClassificationIndustrySearchResult[]
     */
    public function search(string $systemKey, string $versionKey, string $query): array;

    /**
     * @return ClassificationIndustryCode[]
     */
    public function getChildren(string $systemKey, string $versionKey, ?string $code = null): array;

    public function getParent(string $systemKey, string $versionKey, string $code): ?ClassificationIndustryCode;

    /**
     * @return ClassificationIndustryCode[]
     */
    public function getAncestors(string $systemKey, string $versionKey, string $code): array;

    /**
     * @return ClassificationIndustryCode[]
     */
    public function getDescendants(string $systemKey, string $versionKey, string $code): array;

    /**
     * @return ClassificationIndustryCode[]
     */
    public function getLeafCodes(string $systemKey, string $versionKey): array;

    /**
     * @return ClassificationIndustryTranslation[]
     */
    public function getTranslations(string $systemKey, string $versionKey, string $code): array;

    public function getTranslation(string $systemKey, string $versionKey, string $code, string $locale): ?ClassificationIndustryTranslation;
}
