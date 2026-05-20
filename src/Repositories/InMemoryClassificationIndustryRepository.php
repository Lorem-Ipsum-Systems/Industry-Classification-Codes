<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Repositories;

use LoremIpsum\IndustryClassificationCodes\Contracts\ClassificationIndustryRepositoryInterface;
use LoremIpsum\IndustryClassificationCodes\Data\ClassificationIndustryDataSet;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryCode;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustrySearchResult;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustrySystem;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryTranslation;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryVersion;

final class InMemoryClassificationIndustryRepository implements ClassificationIndustryRepositoryInterface
{
    public function __construct(
        private ClassificationIndustryDataSet $dataSet
    ) {
    }

    public function getSystems(): array
    {
        return array_values($this->dataSet->systems);
    }

    public function getVersions(string $systemKey): array
    {
        return array_values($this->dataSet->versions[$systemKey] ?? []);
    }

    public function findCode(string $systemKey, string $versionKey, string $code): ?ClassificationIndustryCode
    {
        return $this->dataSet->findCode($systemKey, $versionKey, $code);
    }

    public function getChildren(string $systemKey, string $versionKey, ?string $parentCode = null): array
    {
        return $this->dataSet->getChildren($systemKey, $versionKey, $parentCode);
    }

    public function getTranslations(string $systemKey, string $versionKey, string $code): array
    {
        return $this->dataSet->getTranslations($systemKey, $versionKey, $code);
    }

    public function search(string $systemKey, string $versionKey, string $term, int $limit = 10): array
    {
        $term = mb_strtolower($term);
        $results = [];

        // Search in codes (titles)
        foreach ($this->dataSet->codes[$systemKey][$versionKey] ?? [] as $code => $model) {
            $searchable = mb_strtolower($model->title);
            if (str_contains($searchable, $term)) {
                $score = $this->calculateScore($searchable, $term);
                $results[$code] = new ClassificationIndustrySearchResult($model, $score, $model->title);
            }
        }

        // Search in search terms
        foreach ($this->dataSet->searchTerms[$systemKey][$versionKey] ?? [] as $code => $terms) {
            foreach ($terms as $st) {
                $searchable = mb_strtolower($st->term);
                if (str_contains($searchable, $term)) {
                    $score = $this->calculateScore($searchable, $term);
                    if (!isset($results[$code]) || $score > $results[$code]->score) {
                        $results[$code] = new ClassificationIndustrySearchResult(
                            $this->dataSet->codes[$systemKey][$versionKey][$code],
                            $score,
                            $st->term
                        );
                    }
                }
            }
        }

        uasort($results, fn($a, $b) => $b->score <=> $a->score);

        return array_slice(array_values($results), 0, $limit);
    }

    public function validateCode(string $systemKey, string $versionKey, string $code): bool
    {
        return $this->findCode($systemKey, $versionKey, $code) !== null;
    }

    private function calculateScore(string $haystack, string $needle): float
    {
        if ($haystack === $needle) return 1.0;
        if (str_starts_with($haystack, $needle)) return 0.8;
        return 0.5;
    }
}
