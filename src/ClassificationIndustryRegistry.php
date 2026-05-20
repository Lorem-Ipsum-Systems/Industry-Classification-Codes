<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes;

use LoremIpsum\IndustryClassificationCodes\Contracts\ClassificationIndustryRepositoryInterface;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryCode;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustrySearchResult;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustrySystem;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryTranslation;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryVersion;

class ClassificationIndustryRegistry
{
    public function __construct(
        private ClassificationIndustryRepositoryInterface $repository
    ) {
    }

    /**
     * @return ClassificationIndustrySystem[]
     */
    public function getSystems(): array
    {
        return $this->repository->getSystems();
    }

    /**
     * @return ClassificationIndustryVersion[]
     */
    public function getVersions(string $systemKey): array
    {
        return $this->repository->getVersions($systemKey);
    }

    public function findCode(string $system, string $version, string $code): ?ClassificationIndustryCode
    {
        return $this->repository->findCode($system, $version, $code);
    }

    public function validateCode(string $system, string $version, string $code): bool
    {
        return $this->repository->validateCode($system, $version, $code);
    }

    /**
     * @return ClassificationIndustryCode[]
     */
    public function getChildren(string $system, string $version, ?string $parentCode = null): array
    {
        return $this->repository->getChildren($system, $version, $parentCode);
    }

    /**
     * @return ClassificationIndustryTranslation[]
     */
    public function getTranslations(string $system, string $version, string $code): array
    {
        return $this->repository->getTranslations($system, $version, $code);
    }

    /**
     * @return ClassificationIndustrySearchResult[]
     */
    public function search(string $system, string $version, string $term, int $limit = 10): array
    {
        return $this->repository->search($system, $version, $term, $limit);
    }
}
