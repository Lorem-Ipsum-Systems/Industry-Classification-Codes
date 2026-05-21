<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Data\Loader;

use LoremIpsum\IndustryClassificationCodes\Data\ClassificationIndustryDataSet;
use LoremIpsum\IndustryClassificationCodes\Data\NdjsonReader;
use LoremIpsum\IndustryClassificationCodes\Data\TextNormalizer;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryCode;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryTranslation;

final class Nace21DataLoader
{
    public function __construct(
        private NdjsonReader $reader,
        private TextNormalizer $textNormalizer
    ) {
    }

    public function load(string $basePath, array $files, ClassificationIndustryDataSet $dataSet): void
    {
        $system = 'NACE';
        $version = '2.1';

        $rawCodes = [];

        // 1. Load Structure and Explanatory Notes
        foreach ($this->reader->read($basePath . '/' . $files['structure']) as $row) {
            $code = (string)$row['code'];

            $includes = $this->textNormalizer->normalize($row['includes'] ?? null);
            $includesAlso = $this->textNormalizer->normalize($row['includes_also'] ?? null);
            $excludes = $this->textNormalizer->normalize($row['excludes'] ?? null);
            $implementationRule = $this->textNormalizer->normalize($row['implementation_rule'] ?? null);

            // Build a rich description
            $descriptionParts = [];
            if ($includes) { $descriptionParts[] = "Includes: " . $includes; }
            if ($includesAlso) { $descriptionParts[] = "Includes also: " . $includesAlso; }
            if ($excludes) { $descriptionParts[] = "Excludes: " . $excludes; }
            if ($implementationRule) { $descriptionParts[] = "Implementation Rule: " . $implementationRule; }

            $description = $descriptionParts ? implode("\n\n", $descriptionParts) : null;

            $level = (int)$row['level'];
            $levelName = match ($level) {
                1 => 'section',
                2 => 'division',
                3 => 'group',
                4 => 'class',
                default => 'unknown',
            };

            $parentCode = (string)($row['parent_code'] ?? '');
            if ($parentCode === '') {
                $parentCode = null;
            }

            $rawCodes[$code] = [
                'code' => $code,
                'title' => $this->textNormalizer->normalize($row['title'] ?? '') ?? '',
                'description' => $description,
                'level' => $level,
                'level_name' => $levelName,
                'parent_code' => $parentCode,
                'is_selectable' => true,
                'includes' => $includes,
                'includes_also' => $includesAlso,
                'excludes' => $excludes,
                'implementation_rule' => $implementationRule,
                'source_file' => $row['source_file'],
            ];
        }

        // 2. Compute isLeaf
        $hasChildren = [];
        foreach ($rawCodes as $code => $data) {
            if ($data['parent_code'] !== null) {
                $hasChildren[$data['parent_code']] = true;
            }
        }

        // 3. Hydrate and add to DataSet
        foreach ($rawCodes as $code => $data) {
            $code = (string)$code;
            $parentCode = $data['parent_code'];

            $model = new ClassificationIndustryCode(
                $system,
                $version,
                $code,
                $code,
                [],
                $data['title'],
                $data['description'],
                $data['level'],
                $data['level_name'],
                $data['level'],
                $parentCode,
                $data['level_name'],
                !isset($hasChildren[$code]),
                $data['is_selectable'],
                true,
                null,
                null,
                null,
                $data['includes'],
                $data['includes_also'],
                $data['excludes'],
                $data['implementation_rule'],
                [$data['source_file']]
            );
            $dataSet->addCode($model);
        }

        // 4. Load Translations
        if (isset($files['headings_all_languages'])) {
            foreach ($this->reader->read($basePath . '/' . $files['headings_all_languages']) as $row) {
                $code = (string)$row['code'];
                if (isset($rawCodes[$code])) {
                    $dataSet->addTranslation(new ClassificationIndustryTranslation(
                        $system,
                        $version,
                        $code,
                        $row['locale'],
                        $this->textNormalizer->normalize($row['title']) ?? '',
                        null,
                        $row['source_file']
                    ));
                }
            }
        }
    }
}
