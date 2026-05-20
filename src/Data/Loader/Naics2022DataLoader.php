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

        $codesData = [];

        // 1. Load Structure
        foreach ($this->reader->read($basePath . '/' . $files['structure']) as $row) {
            $code = (string)$row['code'];
            $codesData[$code] = [
                'code' => $code,
                'title' => $this->textNormalizer->normalize($row['title'], true),
                'level' => $row['level'],
                'parent_code' => (string)($row['parent_code'] ?? '') ?: null,
                'is_leaf' => (bool)$row['is_leaf'],
                'is_selectable' => (bool)$row['is_selectable'],
                'change_indicator' => (string)($row['change_indicator'] ?? '') ?: null,
                'source_files' => [$row['source_file']],
                'description' => null,
                'variant' => null,
            ];
        }

        // 2. Load Descriptions
        if (isset($files['descriptions'])) {
            foreach ($this->reader->read($basePath . '/' . $files['descriptions']) as $row) {
                $code = (string)$row['code'];
                if (isset($codesData[$code])) {
                    $codesData[$code]['description'] = $this->textNormalizer->normalize($row['description']);
                    if (!in_array($row['source_file'], $codesData[$code]['source_files'], true)) {
                        $codesData[$code]['source_files'][] = $row['source_file'];
                    }
                }
            }
        }

        // 3. Load 6-digit variant metadata
        if (isset($files['six_digit_codes'])) {
            foreach ($this->reader->read($basePath . '/' . $files['six_digit_codes']) as $row) {
                $code = (string)$row['code'];
                if (isset($codesData[$code])) {
                    $codesData[$code]['variant'] = $row['variant'] ?? null;
                    if (!in_array($row['source_file'], $codesData[$code]['source_files'], true)) {
                        $codesData[$code]['source_files'][] = $row['source_file'];
                    }
                }
            }
        }

        // 4. Hydrate Codes
        foreach ($codesData as $code => $data) {
            $code = (string)$code;
            $levelName = $this->getNaicsLevelName($code);
            $depth = $this->getNaicsDepth($code);

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
                $data['parent_code'],
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

        // 5. Load Index Terms (Search Terms)
        if (isset($files['index'])) {
            foreach ($this->reader->read($basePath . '/' . $files['index']) as $row) {
                $code = (string)$row['code'];
                if ($code === '******') {
                    continue;
                }

                if (isset($codesData[$code])) {
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
