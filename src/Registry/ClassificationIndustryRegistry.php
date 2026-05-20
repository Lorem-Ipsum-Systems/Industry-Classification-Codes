<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Registry;

use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryCode;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustrySearchResult;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustrySystem;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryTranslation;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryVersion;
use LoremIpsum\IndustryClassificationCodes\Repository\ClassificationIndustryRepositoryInterface;
use LoremIpsum\IndustryClassificationCodes\Repository\ShippedNdjsonClassificationIndustryRepository;
use RuntimeException;

final class ClassificationIndustryRegistry
{
    public function __construct(
        private ClassificationIndustryRepositoryInterface $repository
    ) {
    }

    public static function fromDefaultData(): self
    {
        $dataDir = dirname(__DIR__, 2) . '/data';
        return self::fromDataPath($dataDir);
    }

    public static function fromDataPath(string $path): self
    {
        $repository = new ShippedNdjsonClassificationIndustryRepository($path);
        return new self($repository);
    }

    /**
     * @return ClassificationIndustrySystem[]
     */
    public function systems(): array
    {
        return $this->repository->getSystems();
    }

    /**
     * @return ClassificationIndustryVersion[]
     */
    public function versions(string $system): array
    {
        return $this->repository->getVersions($system);
    }

    public function latestVersion(string $system): ?ClassificationIndustryVersion
    {
        $versions = $this->versions($system);
        foreach ($versions as $version) {
            if ($version->isLatest) {
                return $version;
            }
        }
        return $versions[0] ?? null;
    }

    public function findCode(string $system, string $version, string $code): ?ClassificationIndustryCode
    {
        return $this->repository->findCode($system, $version, $code);
    }

    public function isValidCode(string $system, string $version, string $code): bool
    {
        return $this->repository->isValidCode($system, $version, $code);
    }

    /**
     * @return ClassificationIndustrySearchResult[]
     */
    public function search(string $system, string $version, string $query): array
    {
        return $this->repository->search($system, $version, $query);
    }

    /**
     * @return ClassificationIndustryCode[]
     */
    public function childrenOf(string $system, string $version, ?string $code = null): array
    {
        return $this->repository->getChildren($system, $version, $code);
    }

    public function parentOf(string $system, string $version, string $code): ?ClassificationIndustryCode
    {
        return $this->repository->getParent($system, $version, $code);
    }

    /**
     * @return ClassificationIndustryCode[]
     */
    public function ancestorsOf(string $system, string $version, string $code): array
    {
        return $this->repository->getAncestors($system, $version, $code);
    }

    /**
     * @return ClassificationIndustryCode[]
     */
    public function descendantsOf(string $system, string $version, string $code): array
    {
        return $this->repository->getDescendants($system, $version, $code);
    }

    /**
     * @return ClassificationIndustryCode[]
     */
    public function leafCodes(string $system, string $version): array
    {
        return $this->repository->getLeafCodes($system, $version);
    }

    /**
     * @return ClassificationIndustryTranslation[]
     */
    public function translationsFor(string $system, string $version, string $code): array
    {
        return $this->repository->getTranslations($system, $version, $code);
    }

    public function translationFor(string $system, string $version, string $code, string $locale): ?ClassificationIndustryTranslation
    {
        return $this->repository->getTranslation($system, $version, $code, $locale);
    }
}
