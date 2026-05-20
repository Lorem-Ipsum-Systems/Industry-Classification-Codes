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

        $codesData = [];

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

            $codesData[$code] = true;

            $model = new ClassificationIndustryCode(
                $system,
                $version,
                $code,
                $code,
                [],
                $this->textNormalizer->normalize($row['title'] ?? '') ?? '',
                $description,
                $level,
                $levelName,
                $level,
                (string)($row['parent_code'] ?? '') ?: null,
                $levelName,
                (bool)($row['is_leaf'] ?? false), // We might need to re-evaluate this after loading all codes if we want to be strict
                (bool)($row['is_selectable'] ?? true),
                true,
                null,
                null,
                null,
                $includes,
                $includesAlso,
                $excludes,
                $implementationRule,
                [$row['source_file']]
            );
            $dataSet->addCode($model);
        }

        // 2. Load Translations
        if (isset($files['headings_all_languages'])) {
            foreach ($this->reader->read($basePath . '/' . $files['headings_all_languages']) as $row) {
                $code = (string)$row['code'];
                if (isset($codesData[$code])) {
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
