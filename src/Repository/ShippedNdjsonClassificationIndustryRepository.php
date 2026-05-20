<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Repository;

use LoremIpsum\IndustryClassificationCodes\Data\ClassificationIndustryDataSet;
use LoremIpsum\IndustryClassificationCodes\Data\ShippedClassificationIndustryDataLoader;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryCode;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustrySearchResult;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustrySystem;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryTranslation;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryVersion;
use LoremIpsum\IndustryClassificationCodes\Search\ClassificationIndustrySearch;

final class ShippedNdjsonClassificationIndustryRepository implements ClassificationIndustryRepositoryInterface
{
    private ?ClassificationIndustryDataSet $dataSet = null;
    private ?ClassificationIndustrySearch $searcher = null;

    public function __construct(
        private string $dataRootPath
    ) {
    }

    private function getDataSet(): ClassificationIndustryDataSet
    {
        if ($this->dataSet === null) {
            $loader = new ShippedClassificationIndustryDataLoader($this->dataRootPath);
            $this->dataSet = $loader->load();
        }
        return $this->dataSet;
    }

    private function getSearcher(): ClassificationIndustrySearch
    {
        if ($this->searcher === null) {
            $this->searcher = new ClassificationIndustrySearch($this->getDataSet());
        }
        return $this->searcher;
    }

    public function getSystems(): array
    {
        return array_values($this->getDataSet()->systems);
    }

    public function getVersions(string $systemKey): array
    {
        return array_values($this->getDataSet()->versions[$systemKey] ?? []);
    }

    public function findCode(string $systemKey, string $versionKey, string $code): ?ClassificationIndustryCode
    {
        return $this->getDataSet()->findCode($systemKey, $versionKey, $code);
    }

    public function isValidCode(string $systemKey, string $versionKey, string $code): bool
    {
        return $this->findCode($systemKey, $versionKey, $code) !== null;
    }

    /**
     * @return ClassificationIndustrySearchResult[]
     */
    public function search(string $systemKey, string $versionKey, string $query): array
    {
        return $this->getSearcher()->search($systemKey, $versionKey, $query);
    }

    /**
     * @return ClassificationIndustryCode[]
     */
    public function getChildren(string $systemKey, string $versionKey, ?string $code = null): array
    {
        if ($code !== null) {
            $model = $this->findCode($systemKey, $versionKey, $code);
            if ($model === null) {
                return [];
            }
            $code = $model->code;
        }

        return $this->getDataSet()->getChildren($systemKey, $versionKey, $code);
    }

    public function getParent(string $systemKey, string $versionKey, string $code): ?ClassificationIndustryCode
    {
        $model = $this->findCode($systemKey, $versionKey, $code);
        if ($model === null || $model->parentCode === null) {
            return null;
        }
        return $this->findCode($systemKey, $versionKey, $model->parentCode);
    }

    /**
     * @return ClassificationIndustryCode[]
     */
    public function getAncestors(string $systemKey, string $versionKey, string $code): array
    {
        $ancestors = [];
        $current = $this->findCode($systemKey, $versionKey, $code);

        while ($current !== null && $current->parentCode !== null) {
            $parent = $this->findCode($systemKey, $versionKey, $current->parentCode);
            if ($parent === null) {
                break;
            }
            $ancestors[] = $parent;
            $current = $parent;
        }

        return $ancestors;
    }

    /**
     * @return ClassificationIndustryCode[]
     */
    public function getDescendants(string $systemKey, string $versionKey, string $code): array
    {
        $descendants = [];
        $children = $this->getChildren($systemKey, $versionKey, $code);

        foreach ($children as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, $this->getDescendants($systemKey, $versionKey, $child->code));
        }

        return $descendants;
    }

    /**
     * @return ClassificationIndustryCode[]
     */
    public function getLeafCodes(string $systemKey, string $versionKey): array
    {
        $allCodes = $this->getDataSet()->codes[$systemKey][$versionKey] ?? [];
        return array_filter($allCodes, fn(ClassificationIndustryCode $code) => $code->isLeaf);
    }

    /**
     * @return ClassificationIndustryTranslation[]
     */
    public function getTranslations(string $systemKey, string $versionKey, string $code): array
    {
        $model = $this->findCode($systemKey, $versionKey, $code);
        if ($model === null) {
            return [];
        }
        return $this->getDataSet()->getTranslations($systemKey, $versionKey, $model->code);
    }

    public function getTranslation(string $systemKey, string $versionKey, string $code, string $locale): ?ClassificationIndustryTranslation
    {
        $model = $this->findCode($systemKey, $versionKey, $code);
        if ($model === null) {
            return null;
        }
        return $this->getDataSet()->getTranslation($systemKey, $versionKey, $model->code, $locale);
    }
}
