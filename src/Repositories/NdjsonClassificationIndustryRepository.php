<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Repositories;

use LoremIpsum\IndustryClassificationCodes\Contracts\ClassificationIndustryRepositoryInterface;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryCode;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustrySearchResult;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustrySystem;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryTranslation;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryVersion;

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
                name: $data['name'],
                region: $data['region'],
                description: $data['description']
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
            if ($data['system'] === $systemKey) {
                $versions[] = new ClassificationIndustryVersion(
                    system: $data['system'],
                    version: $data['version'],
                    label: $data['label'],
                    isLatest: $data['is_latest'],
                    dataPath: $data['data_path']
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
            if ($data['code'] === $code || $data['normalized_code'] === $code || in_array($code, $data['aliases'] ?? [])) {
                return $this->hydrateCode($data);
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
                $children[] = $this->hydrateCode($data);
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
                    system: $data['system'],
                    version: $data['version'],
                    code: $data['code'],
                    locale: $data['locale'],
                    title: $data['title'],
                    description: $data['description'] ?? null,
                    sourceFile: $data['source_file']
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

            if (count($results) >= $limit * 3) { // Get more for better sorting
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
                    score: (float)$res['score'],
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

    private function hydrateCode(array $data): ClassificationIndustryCode
    {
        return new ClassificationIndustryCode(
            system: $data['system'],
            version: $data['version'],
            code: $data['code'],
            normalizedCode: $data['normalized_code'],
            aliases: $data['aliases'] ?? [],
            title: $data['title'],
            description: $data['description'] ?? null,
            level: $data['level'] ?? null,
            levelName: $data['level_name'] ?? 'unknown',
            depth: (int)($data['depth'] ?? 0),
            parentCode: $data['parent_code'] ?? null,
            codeType: $data['code_type'] ?? 'unknown',
            isLeaf: (bool)($data['is_leaf'] ?? false),
            isSelectable: (bool)($data['is_selectable'] ?? true),
            isActive: (bool)($data['is_active'] ?? true),
            variant: $data['variant'] ?? null,
            changeIndicator: $data['change_indicator'] ?? null,
            introductoryText: $data['introductory_text'] ?? null,
            includes: $data['includes'] ?? null,
            includesAlso: $data['includes_also'] ?? null,
            excludes: $data['excludes'] ?? null,
            implementationRule: $data['implementation_rule'] ?? null,
            sourceFiles: $data['source_files'] ?? []
        );
    }

    private function readNdjson(string $filePath): \Generator
    {
        $handle = fopen($filePath, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') continue;
                yield json_decode($line, true);
            }
            fclose($handle);
        }
    }

    private function calculateScore(string $haystack, string $needle): float
    {
        if ($haystack === $needle) return 1.0;
        if (str_starts_with($haystack, $needle)) return 0.8;
        return 0.5;
    }
}
