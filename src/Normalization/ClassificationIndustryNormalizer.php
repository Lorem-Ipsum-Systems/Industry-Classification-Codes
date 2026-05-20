<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Normalization;

class ClassificationIndustryNormalizer
{
    public function __construct(
        private string $sourcePath,
        private string $normalizedPath
    ) {
    }

    public function normalize(): void
    {
        $systems = [
            ['key' => 'NAICS', 'name' => 'North American Industry Classification System'],
            ['key' => 'NACE', 'name' => 'Statistical Classification of Economic Activities in the European Community'],
            ['key' => 'UK_SIC', 'name' => 'UK Standard Industrial Classification of Economic Activities'],
            ['key' => 'ISIC', 'name' => 'International Standard Industrial Classification of All Economic Activities'],
        ];

        $versions = [
            ['system_key' => 'NAICS', 'version_key' => '2022', 'name' => 'NAICS 2022'],
            ['system_key' => 'NACE', 'version_key' => '2.1', 'name' => 'NACE Rev. 2.1'],
            ['system_key' => 'UK_SIC', 'version_key' => '2026', 'name' => 'UK SIC 2026'],
            ['system_key' => 'ISIC', 'version_key' => '5', 'name' => 'ISIC Rev. 5'],
        ];

        $this->ensureDirectory($this->normalizedPath);
        $this->writeNdjson($this->normalizedPath . '/systems.ndjson', $systems);
        $this->writeNdjson($this->normalizedPath . '/versions.ndjson', $versions);

        $this->normalizeNaics2022();
        $this->normalizeNace21();
        $this->normalizeUkSic2026();
        $this->normalizeIsic5();
    }

    private function normalizeNaics2022(): void
    {
        $systemKey = 'NAICS';
        $versionKey = '2022';
        $sourceDir = "{$this->sourcePath}/naics/2022";
        $targetDir = "{$this->normalizedPath}/naics/2022";

        if (!is_dir($sourceDir)) return;
        $this->ensureDirectory($targetDir);

        $codes = [];
        $searchTerms = [];

        // 1. Structure
        $structureFile = "$sourceDir/2022_NAICS_Structure.ndjson";
        if (file_exists($structureFile)) {
            foreach ($this->readNdjson($structureFile) as $row) {
                $code = (string)($row['code'] ?? $row['NAICS Code'] ?? '');
                if (!$code) continue;

                $codes[$code] = [
                    'code' => $code,
                    'name' => $row['name'] ?? $row['NAICS Title'] ?? '',
                    'parent_code' => $this->getNaicsParent($code),
                    'metadata' => $row
                ];
            }
        }

        // 2. Descriptions
        $descriptionsFile = "$sourceDir/2022_NAICS_Descriptions.ndjson";
        if (file_exists($descriptionsFile)) {
            foreach ($this->readNdjson($descriptionsFile) as $row) {
                $code = (string)($row['code'] ?? $row['NAICS Code'] ?? '');
                if (isset($codes[$code])) {
                    $codes[$code]['description'] = $row['description'] ?? $row['Description'] ?? '';
                }
            }
        }

        // 3. Index File (for search terms)
        $indexFile = "$sourceDir/2022_NAICS_Index_File.ndjson";
        if (file_exists($indexFile)) {
            foreach ($this->readNdjson($indexFile) as $row) {
                $code = (string)($row['code'] ?? $row['NAICS Code'] ?? '');
                $term = $row['term'] ?? $row['Index Item'] ?? '';
                if ($code && $term) {
                    $searchTerms[] = ['code' => $code, 'term' => $term];
                }
            }
        }

        $this->writeNdjson("$targetDir/codes.ndjson", array_values($codes));
        $this->writeNdjson("$targetDir/search_terms.ndjson", $searchTerms);
    }

    private function normalizeNace21(): void
    {
        $systemKey = 'NACE';
        $versionKey = '2.1';
        $sourceDir = "{$this->sourcePath}/nace/2.1";
        $targetDir = "{$this->normalizedPath}/nace/2.1";

        if (!is_dir($sourceDir)) return;
        $this->ensureDirectory($targetDir);

        $codes = [];
        $translations = [];
        $searchTerms = [];

        // Structure and Explanatory Notes (EN)
        $structureFile = "$sourceDir/NACE_Rev2.1_Structure_Explanatory_Notes_EN.ndjson";
        if (file_exists($structureFile)) {
            foreach ($this->readNdjson($structureFile) as $row) {
                $code = (string)($row['Code'] ?? '');
                if (!$code) continue;

                $codes[$code] = [
                    'code' => $code,
                    'name' => $row['Label'] ?? '',
                    'description' => $row['Explanatory_Note'] ?? null,
                    'parent_code' => $row['Parent'] ?? null,
                    'metadata' => $row
                ];
                
                $searchTerms[] = ['code' => $code, 'term' => $row['Label'] ?? ''];
            }
        }

        // Translations
        $translationsFile = "$sourceDir/NACE_Rev2.1_Heading_All_Languages.ndjson";
        if (file_exists($translationsFile)) {
            foreach ($this->readNdjson($translationsFile) as $row) {
                $code = (string)($row['Code'] ?? '');
                if (!$code) continue;
                
                foreach ($row as $key => $value) {
                    if (strlen($key) === 2) { // language code like 'FR', 'DE'
                        $translations[] = [
                            'code' => $code,
                            'language' => strtolower($key),
                            'name' => $value
                        ];
                    }
                }
            }
        }

        $this->writeNdjson("$targetDir/codes.ndjson", array_values($codes));
        $this->writeNdjson("$targetDir/translations.ndjson", $translations);
        $this->writeNdjson("$targetDir/search_terms.ndjson", $searchTerms);
    }

    private function normalizeUkSic2026(): void
    {
        $systemKey = 'UK_SIC';
        $versionKey = '2026';
        $sourceDir = "{$this->sourcePath}/uk_sic/2026";
        $targetDir = "{$this->normalizedPath}/uk_sic/2026";

        if (!is_dir($sourceDir)) return;
        $this->ensureDirectory($targetDir);

        $codes = [];
        $searchTerms = [];

        $file = "$sourceDir/sic2026classification.ndjson";
        if (file_exists($file)) {
            foreach ($this->readNdjson($file) as $row) {
                $code = (string)($row['SIC Code'] ?? $row['code'] ?? '');
                if (!$code) continue;

                $codes[$code] = [
                    'code' => $code,
                    'name' => $row['Description'] ?? $row['name'] ?? '',
                    'parent_code' => $row['Parent Code'] ?? null,
                    'metadata' => $row
                ];
                $searchTerms[] = ['code' => $code, 'term' => $row['Description'] ?? $row['name'] ?? ''];
            }
        }

        $this->writeNdjson("$targetDir/codes.ndjson", array_values($codes));
        $this->writeNdjson("$targetDir/search_terms.ndjson", $searchTerms);
    }

    private function normalizeIsic5(): void
    {
        $systemKey = 'ISIC';
        $versionKey = '5';
        $sourceDir = "{$this->sourcePath}/isic/5";
        $targetDir = "{$this->normalizedPath}/isic/5";

        if (!is_dir($sourceDir)) return;
        $this->ensureDirectory($targetDir);

        $codes = [];
        $searchTerms = [];

        $structureFile = "$sourceDir/ISIC_Rev_5_english_structure.ndjson";
        if (file_exists($structureFile)) {
            foreach ($this->readNdjson($structureFile) as $row) {
                $code = (string)($row['code'] ?? '');
                if (!$code) continue;

                $codes[$code] = [
                    'code' => $code,
                    'name' => $row['description'] ?? '',
                    'parent_code' => $row['parent'] ?? null,
                    'metadata' => $row
                ];
                $searchTerms[] = ['code' => $code, 'term' => $row['description'] ?? ''];
            }
        }

        $notesFile = "$sourceDir/ISIC5_Exp_Notes_11Mar2024.ndjson";
        if (file_exists($notesFile)) {
            foreach ($this->readNdjson($notesFile) as $row) {
                $code = (string)($row['code'] ?? '');
                if (isset($codes[$code])) {
                    $codes[$code]['description'] = $row['explanatory_note'] ?? '';
                }
            }
        }

        $this->writeNdjson("$targetDir/codes.ndjson", array_values($codes));
        $this->writeNdjson("$targetDir/search_terms.ndjson", $searchTerms);
    }

    private function getNaicsParent(string $code): ?string
    {
        $len = strlen($code);
        if ($len <= 2) return null;
        if ($len === 3) return substr($code, 0, 2);
        if ($len === 4) return substr($code, 0, 3);
        if ($len === 5) return substr($code, 0, 4);
        if ($len === 6) return substr($code, 0, 5);
        return null;
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
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

    private function writeNdjson(string $filePath, array $data): void
    {
        $handle = fopen($filePath, 'w');
        foreach ($data as $row) {
            fwrite($handle, json_encode($row) . "\n");
        }
        fclose($handle);
    }
}
