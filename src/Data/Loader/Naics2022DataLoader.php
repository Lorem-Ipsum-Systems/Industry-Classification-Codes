<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Data\Loader;

use LoremIpsum\IndustryClassificationCodes\Data\ClassificationIndustryDataSet;
use LoremIpsum\IndustryClassificationCodes\Data\NdjsonReader;
use LoremIpsum\IndustryClassificationCodes\Data\TextNormalizer;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryCode;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustrySearchTerm;

final class Naics2022DataLoader
{
    public function __construct(
        private NdjsonReader $reader,
        private TextNormalizer $textNormalizer
    ) {
    }

    public function load(string $basePath, array $files, ClassificationIndustryDataSet $dataSet): void
    {
        $system = 'NAICS';
        $version = '2022';

        $rawCodes = [];

        // 1. Load Structure
        foreach ($this->reader->read($basePath . '/' . $files['structure']) as $row) {
            $code = (string)$row['code'];
            $parentCode = (string)($row['parent_code'] ?? '') ?: null;

            // Range-sector repair
            if (in_array($code, ['31-33', '44-45', '48-49'], true)) {
                $parentCode = null;
            } elseif (in_array($parentCode, ['31-3', '44-4', '48-4'], true)) {
                $parentCode = null;
            }

            if ($parentCode === '31' || $parentCode === '32' || $parentCode === '33') {
                $parentCode = '31-33';
            } elseif ($parentCode === '44' || $parentCode === '45') {
                $parentCode = '44-45';
            } elseif ($parentCode === '48' || $parentCode === '49') {
                $parentCode = '48-49';
            }

            $changeIndicator = (string)($row['change_indicator'] ?? '');
            if ($changeIndicator === '') {
                $changeIndicator = null;
            }

            $rawCodes[$code] = [
                'code' => $code,
                'title' => $this->textNormalizer->normalize($row['title'], true),
                'level' => $row['level'],
                'parent_code' => $parentCode,
                'is_leaf' => false, // Will compute later
                'is_selectable' => true, // All valid codes are selectable in v1
                'change_indicator' => $changeIndicator,
                'source_files' => [$row['source_file']],
                'description' => null,
                'variant' => null,
            ];
        }

        // 2. Load Descriptions
        if (isset($files['descriptions'])) {
            foreach ($this->reader->read($basePath . '/' . $files['descriptions']) as $row) {
                $code = (string)$row['code'];
                if (isset($rawCodes[$code])) {
                    $rawCodes[$code]['description'] = $this->textNormalizer->normalize($row['description']);
                    if (!in_array($row['source_file'], $rawCodes[$code]['source_files'], true)) {
                        $rawCodes[$code]['source_files'][] = $row['source_file'];
                    }
                }
            }
        }

        // 3. Load 6-digit variant metadata
        if (isset($files['six_digit_codes'])) {
            foreach ($this->reader->read($basePath . '/' . $files['six_digit_codes']) as $row) {
                $code = (string)$row['code'];
                if (isset($rawCodes[$code])) {
                    $rawCodes[$code]['variant'] = $row['variant'] ?? null;
                    if (!in_array($row['source_file'], $rawCodes[$code]['source_files'], true)) {
                        $rawCodes[$code]['source_files'][] = $row['source_file'];
                    }
                }
            }
        }

        // 4. Compute isLeaf
        $hasChildren = [];
        foreach ($rawCodes as $code => $data) {
            if ($data['parent_code'] !== null) {
                $hasChildren[$data['parent_code']] = true;
            }
        }
        foreach ($rawCodes as $code => &$data) {
            $data['is_leaf'] = !isset($hasChildren[$code]);
        }
        unset($data);

        // 5. Hydrate and add to DataSet
        foreach ($rawCodes as $code => $data) {
            $code = (string)$code;
            $levelName = $this->getNaicsLevelName($code);
            $depth = $this->getNaicsDepth($code);

            $parentCode = $data['parent_code'];

            $model = new ClassificationIndustryCode(
                $system,
                $version,
                $data['code'],
                $data['code'],
                [],
                $data['title'] ?? '',
                $data['description'],
                $data['level'],
                $levelName,
                $depth,
                $parentCode,
                $levelName,
                $data['is_leaf'],
                $data['is_selectable'],
                true,
                $data['variant'],
                $data['change_indicator'],
                null,
                null,
                null,
                null,
                null,
                $data['source_files']
            );
            $dataSet->addCode($model);
        }

        // 6. Load Index Terms (Search Terms)
        if (isset($files['index'])) {
            foreach ($this->reader->read($basePath . '/' . $files['index']) as $row) {
                $code = (string)$row['code'];
                if ($code === '******') {
                    continue;
                }

                if (isset($rawCodes[$code])) {
                    $term = $this->textNormalizer->normalize($row['search_term']);
                    if ($term) {
                        $dataSet->addSearchTerm(new ClassificationIndustrySearchTerm(
                            $system,
                            $version,
                            $code,
                            $term,
                            '2022_NAICS_Index_File',
                            $row['source_file']
                        ));
                    }
                }
            }
        }
    }

    private function getNaicsLevelName(string $code): string
    {
        $len = strlen($code);
        if (str_contains($code, '-')) {
            return 'sector';
        }
        return match ($len) {
            2 => 'sector',
            3 => 'subsector',
            4 => 'industry_group',
            5 => 'naics_industry',
            6 => 'national_industry',
            default => 'unknown',
        };
    }

    private function getNaicsDepth(string $code): int
    {
        $len = strlen($code);
        if (str_contains($code, '-')) {
            return 1;
        }
        return match ($len) {
            2 => 1,
            3 => 2,
            4 => 3,
            5 => 4,
            6 => 5,
            default => 0,
        };
    }
}
