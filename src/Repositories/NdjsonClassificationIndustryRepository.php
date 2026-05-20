<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Repositories;

use LoremIpsum\IndustryClassificationCodes\Contracts\ClassificationIndustryRepositoryInterface;
use LoremIpsum\IndustryClassificationCodes\Models\ClassificationIndustryCode;
use LoremIpsum\IndustryClassificationCodes\Models\ClassificationIndustrySearchResult;
use LoremIpsum\IndustryClassificationCodes\Models\ClassificationIndustrySystem;
use LoremIpsum\IndustryClassificationCodes\Models\ClassificationIndustryTranslation;
use LoremIpsum\IndustryClassificationCodes\Models\ClassificationIndustryVersion;

class NdjsonClassificationIndustryRepository implements ClassificationIndustryRepositoryInterface
{
    public function __construct(
        private string $normalizedDataPath
    ) {
    }

    public function getSystems(): array
    {
        $file = $this->normalizedDataPath . '/systems.ndjson';
        if (!file_exists($file)) {
            return [];
        }

        $systems = [];
        foreach ($this->readNdjson($file) as $data) {
            $systems[] = new ClassificationIndustrySystem(
                key: $data['key'],
                name: $data['name']
            );
        }

        return $systems;
    }

    public function getVersions(string $systemKey): array
    {
        $file = $this->normalizedDataPath . '/versions.ndjson';
        if (!file_exists($file)) {
            return [];
        }

        $versions = [];
        foreach ($this->readNdjson($file) as $data) {
            if ($data['system_key'] === $systemKey) {
                $versions[] = new ClassificationIndustryVersion(
                    systemKey: $data['system_key'],
                    versionKey: $data['version_key'],
                    name: $data['name']
                );
            }
        }

        return $versions;
    }

    public function findCode(string $systemKey, string $versionKey, string $code): ?ClassificationIndustryCode
    {
        $file = $this->getCodeFilePath($systemKey, $versionKey);
        if (!file_exists($file)) {
            return null;
        }

        foreach ($this->readNdjson($file) as $data) {
            if ($data['code'] === $code) {
                return $this->hydrateCode($data, $systemKey, $versionKey);
            }
        }

        return null;
    }

    public function getChildren(string $systemKey, string $versionKey, ?string $parentCode = null): array
    {
        $file = $this->getCodeFilePath($systemKey, $versionKey);
        if (!file_exists($file)) {
            return [];
        }

        $children = [];
        foreach ($this->readNdjson($file) as $data) {
            if (($data['parent_code'] ?? null) === $parentCode) {
                $children[] = $this->hydrateCode($data, $systemKey, $versionKey);
            }
        }

        return $children;
    }

    public function getTranslations(string $systemKey, string $versionKey, string $code): array
    {
        $file = $this->getTranslationsFilePath($systemKey, $versionKey);
        if (!file_exists($file)) {
            return [];
        }

        $translations = [];
        foreach ($this->readNdjson($file) as $data) {
            if ($data['code'] === $code) {
                $translations[] = new ClassificationIndustryTranslation(
                    systemKey: $systemKey,
                    versionKey: $versionKey,
                    code: $data['code'],
                    language: $data['language'],
                    name: $data['name'],
                    description: $data['description'] ?? null
                );
            }
        }

        return $translations;
    }

    public function search(string $systemKey, string $versionKey, string $term, int $limit = 10): array
    {
        $searchTermsFile = $this->getSearchTermsFilePath($systemKey, $versionKey);
        if (!file_exists($searchTermsFile)) {
            return [];
        }

        $results = [];
        $term = mb_strtolower($term);

        foreach ($this->readNdjson($searchTermsFile) as $data) {
            $searchable = mb_strtolower($data['term']);
            if (str_contains($searchable, $term)) {
                $results[$data['code']] = [
                    'code' => $data['code'],
                    'matchedTerm' => $data['term'],
                    'score' => $this->calculateScore($searchable, $term)
                ];
            }

            if (count($results) >= $limit * 2) { // Get more for better sorting
                break;
            }
        }

        // Sort by score
        uasort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        $results = array_slice($results, 0, $limit);

        $finalResults = [];
        foreach ($results as $res) {
            $code = $this->findCode($systemKey, $versionKey, $res['code']);
            if ($code) {
                $finalResults[] = new ClassificationIndustrySearchResult(
                    code: $code,
                    score: $res['score'],
                    matchedTerm: $res['matchedTerm']
                );
            }
        }

        return $finalResults;
    }

    public function validateCode(string $systemKey, string $versionKey, string $code): bool
    {
        return $this->findCode($systemKey, $versionKey, $code) !== null;
    }

    private function getCodeFilePath(string $systemKey, string $versionKey): string
    {
        return sprintf(
            '%s/%s/%s/codes.ndjson',
            $this->normalizedDataPath,
            strtolower($systemKey),
            $versionKey
        );
    }

    private function getTranslationsFilePath(string $systemKey, string $versionKey): string
    {
        return sprintf(
            '%s/%s/%s/translations.ndjson',
            $this->normalizedDataPath,
            strtolower($systemKey),
            $versionKey
        );
    }

    private function getSearchTermsFilePath(string $systemKey, string $versionKey): string
    {
        return sprintf(
            '%s/%s/%s/search_terms.ndjson',
            $this->normalizedDataPath,
            strtolower($systemKey),
            $versionKey
        );
    }

    private function hydrateCode(array $data, string $systemKey, string $versionKey): ClassificationIndustryCode
    {
        return new ClassificationIndustryCode(
            systemKey: $systemKey,
            versionKey: $versionKey,
            code: $data['code'],
            name: $data['name'],
            parentCode: $data['parent_code'] ?? null,
            description: $data['description'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }

    private function readNdjson(string $filePath): \Generator
    {
        $handle = fopen($filePath, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                yield json_decode($line, true);
            }
            fclose($handle);
        }
    }

    private function calculateScore(string $haystack, string $needle): float
    {
        if ($haystack === $needle) {
            return 1.0;
        }
        if (str_starts_with($haystack, $needle)) {
            return 0.8;
        }
        return 0.5;
    }
}
