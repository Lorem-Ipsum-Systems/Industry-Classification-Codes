<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Search;

use LoremIpsum\IndustryClassificationCodes\Data\ClassificationIndustryDataSet;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryCode;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustrySearchResult;

final class ClassificationIndustrySearch
{
    public function __construct(
        private ClassificationIndustryDataSet $dataSet
    ) {
    }

    /**
     * @return ClassificationIndustrySearchResult[]
     */
    public function search(string $systemKey, string $versionKey, string $query, int $limit = 50): array
    {
        $query = mb_strtolower(trim($query));
        if ($query === '') {
            return [];
        }

        $results = [];

        $codes = $this->dataSet->codes[$systemKey][$versionKey] ?? [];

        foreach ($codes as $code => $model) {
            $code = (string)$code;
            $score = 0.0;
            $matchedField = '';

            // Exact canonical code match (highest)
            if (mb_strtolower($model->code) === $query) {
                $score = 10.0;
                $matchedField = 'code';
            }
            // Exact alias match
            elseif (in_array($query, array_map('mb_strtolower', $model->aliases), true)) {
                $score = 9.0;
                $matchedField = 'alias';
            }
            // Code prefix match
            elseif (str_starts_with(mb_strtolower($model->code), $query)) {
                $score = 8.0;
                $matchedField = 'code_prefix';
            }
            // Title match
            elseif (($titleScore = $this->matchText(mb_strtolower($model->title), $query)) > 0) {
                $score = 7.0 * $titleScore;
                $matchedField = 'title';
            }
            // Search terms match
            elseif (($searchTermScore = $this->matchSearchTerms($systemKey, $versionKey, $code, $query)) > 0) {
                $score = 6.0 * $searchTermScore;
                $matchedField = 'search_term';
            }
            // Translated titles match
            elseif (($translationScore = $this->matchTranslations($systemKey, $versionKey, $code, $query)) > 0) {
                $score = 5.0 * $translationScore;
                $matchedField = 'translation';
            }
            // Includes/Excludes match
            elseif (($notesScore = $this->matchNotes($model, $query)) > 0) {
                $score = 4.0 * $notesScore;
                $matchedField = 'notes';
            }
            // Description match
            elseif (($descScore = $this->matchText(mb_strtolower($model->description ?? ''), $query)) > 0) {
                $score = 3.0 * $descScore;
                $matchedField = 'description';
            }

            if ($score > 0) {
                $results[$code] = new ClassificationIndustrySearchResult($model, $score, $matchedField);
            }
        }

        uasort($results, fn($a, $b) => $b->score <=> $a->score ?: $a->code->code <=> $b->code->code);

        return array_slice(array_values($results), 0, $limit);
    }

    private function matchText(string $haystack, string $needle): float
    {
        if ($haystack === $needle) {
            return 1.0;
        }
        if (str_contains($haystack, $needle)) {
            return 0.5;
        }
        return 0.0;
    }

    private function matchSearchTerms(string $system, string $version, string $code, string $query): float
    {
        $terms = $this->dataSet->getSearchTerms($system, $version, $code);
        $best = 0.0;
        foreach ($terms as $st) {
            $score = $this->matchText(mb_strtolower($st->term), $query);
            if ($score > $best) {
                $best = $score;
            }
        }
        return $best;
    }

    private function matchTranslations(string $system, string $version, string $code, string $query): float
    {
        $translations = $this->dataSet->getTranslations($system, $version, $code);
        $best = 0.0;
        foreach ($translations as $t) {
            $score = $this->matchText(mb_strtolower($t->title), $query);
            if ($score > $best) {
                $best = $score;
            }
        }
        return $best;
    }

    private function matchNotes(ClassificationIndustryCode $model, string $query): float
    {
        $fields = [
            $model->includes,
            $model->includesAlso,
            $model->excludes,
            $model->implementationRule,
            $model->introductoryText,
        ];

        $best = 0.0;
        foreach ($fields as $field) {
            if ($field === null) continue;
            $score = $this->matchText(mb_strtolower($field), $query);
            if ($score > $best) {
                $best = $score;
            }
        }
        return $best;
    }
}
