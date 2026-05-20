<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Data;

use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryCode;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustrySearchTerm;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustrySystem;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryTranslation;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryVersion;

final class ClassificationIndustryDataSet
{
    /** @var ClassificationIndustrySystem[] */
    public array $systems = [];

    /** @var ClassificationIndustryVersion[] */
    public array $versions = [];

    /** @var array<string, array<string, array<string, ClassificationIndustryCode>>> system -> version -> code -> model */
    public array $codes = [];

    /** @var array<string, array<string, array<string, string>>> system -> version -> alias -> canonical_code */
    public array $aliases = [];

    /** @var array<string, array<string, array<string, ClassificationIndustryCode[]>>> system -> version -> parent_code -> children */
    public array $children = [];

    /** @var array<string, array<string, array<string, array<string, ClassificationIndustryTranslation>>>> system -> version -> code -> locale -> model */
    public array $translations = [];

    /** @var array<string, array<string, array<string, ClassificationIndustrySearchTerm[]>>> system -> version -> code -> search_terms */
    public array $searchTerms = [];

    public function addSystem(ClassificationIndustrySystem $system): void
    {
        $this->systems[$system->key] = $system;
    }

    public function addVersion(ClassificationIndustryVersion $version): void
    {
        $this->versions[$version->system][$version->version] = $version;
    }

    public function addCode(ClassificationIndustryCode $code): void
    {
        $this->codes[$code->system][$code->version][$code->code] = $code;

        foreach ($code->aliases as $alias) {
            $this->aliases[$code->system][$code->version][$alias] = $code->code;
        }

        $parentCode = $code->parentCode ?? '';
        $this->children[$code->system][$code->version][$parentCode][] = $code;
    }

    public function addTranslation(ClassificationIndustryTranslation $translation): void
    {
        $this->translations[$translation->system][$translation->version][$translation->code][$translation->locale] = $translation;
    }

    public function addSearchTerm(ClassificationIndustrySearchTerm $searchTerm): void
    {
        $this->searchTerms[$searchTerm->system][$searchTerm->version][$searchTerm->code][] = $searchTerm;
    }

    public function findCode(string $system, string $version, string $code): ?ClassificationIndustryCode
    {
        // Try canonical
        if (isset($this->codes[$system][$version][$code])) {
            return $this->codes[$system][$version][$code];
        }

        // Try alias
        $canonical = $this->aliases[$system][$version][$code] ?? null;
        if ($canonical && isset($this->codes[$system][$version][$canonical])) {
            return $this->codes[$system][$version][$canonical];
        }

        return null;
    }

    /**
     * @return ClassificationIndustryCode[]
     */
    public function getChildren(string $system, string $version, ?string $parentCode = null): array
    {
        return $this->children[$system][$version][$parentCode ?? ''] ?? [];
    }

    /**
     * @return ClassificationIndustryTranslation[]
     */
    public function getTranslations(string $system, string $version, string $code): array
    {
        return array_values($this->translations[$system][$version][$code] ?? []);
    }

    public function getTranslation(string $system, string $version, string $code, string $locale): ?ClassificationIndustryTranslation
    {
        return $this->translations[$system][$version][$code][$locale] ?? null;
    }

    /**
     * @return ClassificationIndustrySearchTerm[]
     */
    public function getSearchTerms(string $system, string $version, string $code): array
    {
        return $this->searchTerms[$system][$version][$code] ?? [];
    }
}
