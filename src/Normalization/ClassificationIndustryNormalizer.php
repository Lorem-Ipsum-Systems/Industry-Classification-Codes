<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Normalization;

class ClassificationIndustryNormalizer
{
    private TextNormalizer $textNormalizer;

    public function __construct(
        private string $sourcePath,
        private string $normalizedPath
    ) {
        $this->textNormalizer = new TextNormalizer();
    }

    public function normalize(): void
    {
        $systems = [
            [
                'key' => 'NAICS',
                'name' => 'North American Industry Classification System',
                'region' => 'US',
                'description' => 'Industry classification system used by the United States, Canada, and Mexico.'
            ],
            [
                'key' => 'NACE',
                'name' => 'Statistical Classification of Economic Activities in the European Community',
                'region' => 'EU',
                'description' => 'The standard for industrial classifications in the European Union.'
            ],
            [
                'key' => 'UK_SIC',
                'name' => 'UK Standard Industrial Classification of Economic Activities',
                'region' => 'UK',
                'description' => 'Standard industrial classification used in the United Kingdom.'
            ],
            [
                'key' => 'ISIC',
                'name' => 'International Standard Industrial Classification of All Economic Activities',
                'region' => 'Global',
                'description' => 'International standard for industrial classifications maintained by the United Nations.'
            ],
        ];

        $versions = [
            [
                'system' => 'NAICS',
                'version' => '2022',
                'label' => 'NAICS 2022',
                'is_latest' => true,
                'data_path' => 'naics/2022'
            ],
            [
                'system' => 'NACE',
                'version' => '2.1',
                'label' => 'NACE Rev. 2.1',
                'is_latest' => true,
                'data_path' => 'nace/2.1'
            ],
            [
                'system' => 'UK_SIC',
                'version' => '2026',
                'label' => 'UK SIC 2026',
                'is_latest' => true,
                'data_path' => 'uk_sic/2026'
            ],
            [
                'system' => 'ISIC',
                'version' => '5',
                'label' => 'ISIC Rev. 5',
                'is_latest' => true,
                'data_path' => 'isic/5'
            ],
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
        $system = 'NAICS';
        $version = '2022';
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
                $code = (string)($row['code'] ?? '');
                if (!$code) continue;

                $title = $this->textNormalizer->normalize($row['title'] ?? '', true);
                $parentCode = $row['parent_code'] ?? $this->getNaicsParent($code);

                $codes[$code] = $this->createBaseCode($system, $version, $code, $title);
                $codes[$code]['parent_code'] = $parentCode;
                $codes[$code]['level'] = $row['level'] ?? null;
                $codes[$code]['change_indicator'] = $row['change_indicator'] ?? null;
                $this->addSourceFile($codes[$code], $row['source_file'] ?? null);

                // NAICS Level Logic
                $len = strlen($code);
                if (str_contains($code, '-')) {
                    $codes[$code]['level_name'] = 'sector';
                    $codes[$code]['depth'] = 1;
                } elseif ($len === 2) {
                    $codes[$code]['level_name'] = 'sector';
                    $codes[$code]['depth'] = 1;
                } elseif ($len === 3) {
                    $codes[$code]['level_name'] = 'subsector';
                    $codes[$code]['depth'] = 2;
                } elseif ($len === 4) {
                    $codes[$code]['level_name'] = 'industry_group';
                    $codes[$code]['depth'] = 3;
                } elseif ($len === 5) {
                    $codes[$code]['level_name'] = 'naics_industry';
                    $codes[$code]['depth'] = 4;
                } elseif ($len === 6) {
                    $codes[$code]['level_name'] = 'national_industry';
                    $codes[$code]['depth'] = 5;
                }
                $codes[$code]['code_type'] = $codes[$code]['level_name'];
            }
        }

        // 2. Descriptions
        $descriptionsFile = "$sourceDir/2022_NAICS_Descriptions.ndjson";
        if (file_exists($descriptionsFile)) {
            foreach ($this->readNdjson($descriptionsFile) as $row) {
                $code = (string)($row['code'] ?? '');
                if (isset($codes[$code])) {
                    $desc = $this->textNormalizer->normalize($row['description'] ?? null);
                    $codes[$code]['description'] = $desc;
                    $this->addSourceFile($codes[$code], $row['source_file'] ?? null);
                }
            }
        }

        // 3. Index File (for search terms)
        $indexFile = "$sourceDir/2022_NAICS_Index_File.ndjson";
        if (file_exists($indexFile)) {
            foreach ($this->readNdjson($indexFile) as $row) {
                $code = (string)($row['code'] ?? '');
                $term = $this->textNormalizer->normalize($row['search_term'] ?? '');
                if ($code === '******') continue;
                if ($code && $term) {
                    $searchTerms[] = [
                        'system' => $system,
                        'version' => $version,
                        'code' => $code,
                        'term' => $term,
                        'source' => '2022_NAICS_Index_File',
                        'source_file' => $row['source_file'] ?? '2022_NAICS_Index_File.xlsx'
                    ];
                }
            }
        }

        // 4. 6-digit leaf reference
        $sixDigitFile = "$sourceDir/6-digit_2022_Codes.ndjson";
        if (file_exists($sixDigitFile)) {
            foreach ($this->readNdjson($sixDigitFile) as $row) {
                $code = (string)($row['code'] ?? '');
                if (isset($codes[$code])) {
                    $codes[$code]['variant'] = $row['variant'] ?? 'US';
                    $this->addSourceFile($codes[$code], $row['source_file'] ?? null);
                }
            }
        }

        $this->finalizeCodes($codes);
        $this->finalizeGeneric($searchTerms, ['code', 'term']);
        $this->writeNdjson("$targetDir/codes.ndjson", $codes);
        $this->writeNdjson("$targetDir/search_terms.ndjson", $searchTerms);
    }

    private function normalizeNace21(): void
    {
        $system = 'NACE';
        $version = '2.1';
        $sourceDir = "{$this->sourcePath}/nace/2.1";
        $targetDir = "{$this->normalizedPath}/nace/2.1";

        if (!is_dir($sourceDir)) return;
        $this->ensureDirectory($targetDir);

        $codes = [];
        $translations = [];
        $searchTerms = [];

        // 1. Structure and Explanatory Notes (EN)
        $structureFile = "$sourceDir/NACE_Rev2.1_Structure_Explanatory_Notes_EN.ndjson";
        if (file_exists($structureFile)) {
            foreach ($this->readNdjson($structureFile) as $row) {
                $code = (string)($row['code'] ?? '');
                if (!$code) continue;

                $title = $this->textNormalizer->normalize($row['title'] ?? '');
                $codes[$code] = $this->createBaseCode($system, $version, $code, $title);
                $codes[$code]['parent_code'] = $row['parent_code'] ?? null;
                $codes[$code]['level'] = $row['level'] ?? null;
                $codes[$code]['includes'] = $this->textNormalizer->normalize($row['includes'] ?? null);
                $codes[$code]['includes_also'] = $this->textNormalizer->normalize($row['includes_also'] ?? null);
                $codes[$code]['excludes'] = $this->textNormalizer->normalize($row['excludes'] ?? null);
                $codes[$code]['implementation_rule'] = $this->textNormalizer->normalize($row['implementation_rule'] ?? null);
                $this->addSourceFile($codes[$code], $row['source_file'] ?? null);

                // Description construction
                $codes[$code]['description'] = $this->buildDescription($codes[$code]);

                // NACE Level Logic
                $level = (int)($row['level'] ?? 0);
                $codes[$code]['depth'] = $level;
                $codes[$code]['level_name'] = match($level) {
                    1 => 'section',
                    2 => 'division',
                    3 => 'group',
                    4 => 'class',
                    default => 'unknown'
                };
                $codes[$code]['code_type'] = $codes[$code]['level_name'];

                $searchTerms[] = [
                    'system' => $system,
                    'version' => $version,
                    'code' => $code,
                    'term' => $title,
                    'source' => 'NACE_Structure',
                    'source_file' => $row['source_file'] ?? 'NACE_Rev2.1_Structure_Explanatory_Notes_EN.xlsx'
                ];
            }
        }

        // 2. Translations
        $translationsFile = "$sourceDir/NACE_Rev2.1_Heading_All_Languages.ndjson";
        if (file_exists($translationsFile)) {
            foreach ($this->readNdjson($translationsFile) as $row) {
                $code = (string)($row['code'] ?? '');
                $locale = strtolower((string)($row['locale'] ?? ''));
                $title = $this->textNormalizer->normalize($row['title'] ?? '');

                if ($code && $locale && $title) {
                    $translations[] = [
                        'system' => $system,
                        'version' => $version,
                        'code' => $code,
                        'locale' => $locale,
                        'title' => $title,
                        'description' => null,
                        'source_file' => $row['source_file'] ?? 'NACE_Rev2.1_Heading_All_Languages.xlsx'
                    ];
                }
            }
        }

        $this->finalizeCodes($codes);
        $this->finalizeGeneric($translations, ['code', 'locale']);
        $this->finalizeGeneric($searchTerms, ['code', 'term']);
        $this->writeNdjson("$targetDir/codes.ndjson", $codes);
        $this->writeNdjson("$targetDir/translations.ndjson", $translations);
        $this->writeNdjson("$targetDir/search_terms.ndjson", $searchTerms);
    }

    private function normalizeUkSic2026(): void
    {
        $system = 'UK_SIC';
        $version = '2026';
        $sourceDir = "{$this->sourcePath}/uk_sic/2026";
        $targetDir = "{$this->normalizedPath}/uk_sic/2026";

        if (!is_dir($sourceDir)) return;
        $this->ensureDirectory($targetDir);

        $codes = [];
        $searchTerms = [];

        $file = "$sourceDir/sic2026classification.ndjson";
        if (file_exists($file)) {
            $lastSectionCode = null;
            foreach ($this->readNdjson($file) as $row) {
                $code = (string)($row['code'] ?? '');
                if (!$code) continue;

                $level = (int)($row['level'] ?? 0);
                if ($level === 1) {
                    $lastSectionCode = $code;
                }

                $parentCode = $row['parent_code'] ?? null;
                // Repair division parent_code (level 2) if it's not a real SIC code (like "0", "1")
                if ($parentCode !== null && !preg_match('/^[A-Z]|[0-9]{2}/', (string)$parentCode) && $level === 2) {
                    $parentCode = $lastSectionCode;
                }

                $title = $this->textNormalizer->normalize($row['title'] ?? '');
                $codes[$code] = $this->createBaseCode($system, $version, $code, $title);
                $codes[$code]['parent_code'] = (string)$parentCode ?: null;
                $codes[$code]['level'] = $level;
                $codes[$code]['includes'] = $this->textNormalizer->normalize($row['includes'] ?? null);
                $codes[$code]['includes_also'] = $this->textNormalizer->normalize($row['includes_also'] ?? null);
                $codes[$code]['excludes'] = $this->textNormalizer->normalize($row['excludes'] ?? null);
                $this->addSourceFile($codes[$code], $row['source_file'] ?? null);

                $codes[$code]['description'] = $this->buildDescription($codes[$code]);

                // UK SIC Level Logic
                $codes[$code]['depth'] = $level;
                $codes[$code]['level_name'] = match($level) {
                    1 => 'section',
                    2 => 'division',
                    3 => 'group',
                    4 => 'class',
                    5 => 'subclass',
                    default => 'unknown'
                };
                $codes[$code]['code_type'] = $codes[$code]['level_name'];

                $searchTerms[] = [
                    'system' => $system,
                    'version' => $version,
                    'code' => $code,
                    'term' => $title,
                    'source' => 'UK_SIC_Structure',
                    'source_file' => $row['source_file'] ?? 'sic2026classification.xlsx'
                ];
            }
        }

        $this->finalizeCodes($codes);
        $this->finalizeGeneric($searchTerms, ['code', 'term']);
        $this->writeNdjson("$targetDir/codes.ndjson", $codes);
        $this->writeNdjson("$targetDir/search_terms.ndjson", $searchTerms);
    }

    private function normalizeIsic5(): void
    {
        $system = 'ISIC';
        $version = '5';
        $sourceDir = "{$this->sourcePath}/isic/5";
        $targetDir = "{$this->normalizedPath}/isic/5";

        if (!is_dir($sourceDir)) return;
        $this->ensureDirectory($targetDir);

        $codes = [];
        $searchTerms = [];

        // 1. Structure
        $structureFile = "$sourceDir/ISIC_Rev_5_english_structure.ndjson";
        if (file_exists($structureFile)) {
            foreach ($this->readNdjson($structureFile) as $row) {
                $code = (string)($row['code'] ?? '');
                if (!$code) continue;

                $title = $this->textNormalizer->normalize($row['title'] ?? '');
                $codes[$code] = $this->createBaseCode($system, $version, $code, $title);
                $codes[$code]['parent_code'] = $row['parent_code'] ?? null;
                $codes[$code]['level'] = $row['level'] ?? null;
                $this->addSourceFile($codes[$code], $row['source_file'] ?? null);

                // ISIC Level Logic
                $level = (int)($row['level'] ?? 0);
                $codes[$code]['depth'] = $level;
                $codes[$code]['level_name'] = match($level) {
                    1 => 'section',
                    2 => 'division',
                    3 => 'group',
                    4 => 'class',
                    default => 'unknown'
                };
                $codes[$code]['code_type'] = $codes[$code]['level_name'];

                $searchTerms[] = [
                    'system' => $system,
                    'version' => $version,
                    'code' => $code,
                    'term' => $title,
                    'source' => 'ISIC_Structure',
                    'source_file' => $row['source_file'] ?? 'ISIC_Rev_5_english_structure.xlsx'
                ];
            }
        }

        // 2. Explanatory Notes
        $notesFile = "$sourceDir/ISIC5_Exp_Notes_11Mar2024.ndjson";
        if (file_exists($notesFile)) {
            foreach ($this->readNdjson($notesFile) as $row) {
                $rawCode = (string)($row['code'] ?? '');
                $normalizedCode = $this->normalizeIsicCode($rawCode);

                if (isset($codes[$normalizedCode])) {
                    $codes[$normalizedCode]['introductory_text'] = $this->textNormalizer->normalize($row['introductory_text'] ?? null);
                    $codes[$normalizedCode]['includes'] = $this->textNormalizer->normalize($row['includes'] ?? null);
                    $codes[$normalizedCode]['includes_also'] = $this->textNormalizer->normalize($row['includes_also'] ?? null);
                    $codes[$normalizedCode]['excludes'] = $this->textNormalizer->normalize($row['excludes'] ?? null);
                    $this->addSourceFile($codes[$normalizedCode], $row['source_file'] ?? null);

                    if ($rawCode !== $normalizedCode && !in_array($rawCode, $codes[$normalizedCode]['aliases'])) {
                        $codes[$normalizedCode]['aliases'][] = $rawCode;
                    }

                    $codes[$normalizedCode]['description'] = $this->buildDescription($codes[$normalizedCode]);
                }
            }
        }

        $this->finalizeCodes($codes);
        $this->finalizeGeneric($searchTerms, ['code', 'term']);
        $this->writeNdjson("$targetDir/codes.ndjson", $codes);
        $this->writeNdjson("$targetDir/search_terms.ndjson", $searchTerms);
    }

    private function createBaseCode(string $system, string $version, string $code, ?string $title): array
    {
        return [
            'system' => $system,
            'version' => $version,
            'code' => $code,
            'normalized_code' => $code,
            'aliases' => [],
            'title' => $title,
            'description' => null,
            'level' => null,
            'level_name' => 'unknown',
            'depth' => 0,
            'parent_code' => null,
            'code_type' => 'unknown',
            'is_leaf' => false,
            'is_selectable' => true,
            'is_active' => true,
            'variant' => null,
            'change_indicator' => null,
            'introductory_text' => null,
            'includes' => null,
            'includes_also' => null,
            'excludes' => null,
            'implementation_rule' => null,
            'source_files' => []
        ];
    }

    private function addSourceFile(array &$code, ?string $file): void
    {
        if ($file && !in_array($file, $code['source_files'])) {
            $code['source_files'][] = $file;
        }
    }

    private function buildDescription(array $code): ?string
    {
        $parts = [];
        if ($code['introductory_text']) $parts[] = $code['introductory_text'];
        if ($code['includes']) $parts[] = "Includes: " . $code['includes'];
        if ($code['includes_also']) $parts[] = "Includes also: " . $code['includes_also'];
        if ($code['excludes']) $parts[] = "Excludes: " . $code['excludes'];
        if ($code['implementation_rule']) $parts[] = "Rules: " . $code['implementation_rule'];

        return $parts ? implode("\n", $parts) : null;
    }

    private function finalizeCodes(array &$codes): void
    {
        // Compute is_leaf
        $parentCodes = [];
        foreach ($codes as $code) {
            if ($code['parent_code'] !== null) {
                $parentCodes[(string)$code['parent_code']] = true;
            }
        }
        foreach ($codes as &$code) {
            $code['is_leaf'] = !isset($parentCodes[(string)$code['code']]);
            sort($code['aliases']);
            sort($code['source_files']);
        }

        // Sort by code
        ksort($codes);
        $codes = array_values($codes);
    }

    private function finalizeGeneric(array &$items, array $sortKeys): void
    {
        usort($items, function ($a, $b) use ($sortKeys) {
            foreach ($sortKeys as $key) {
                $valA = $a[$key] ?? '';
                $valB = $b[$key] ?? '';
                if ($valA !== $valB) {
                    return $valA <=> $valB;
                }
            }
            return 0;
        });
    }

    private function normalizeIsicCode(string $code): string
    {
        if (preg_match('/^[A-Z](\d+)$/', $code, $matches)) {
            return $matches[1];
        }
        return $code;
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
            fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE) . "\n");
        }
        fclose($handle);
    }
}
