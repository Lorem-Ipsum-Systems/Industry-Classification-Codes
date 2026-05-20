<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Data\Loader;

use LoremIpsum\IndustryClassificationCodes\Data\ClassificationIndustryDataSet;
use LoremIpsum\IndustryClassificationCodes\Data\NdjsonReader;
use LoremIpsum\IndustryClassificationCodes\Data\TextNormalizer;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryCode;

final class UkSic2026DataLoader
{
    public function __construct(
        private NdjsonReader $reader,
        private TextNormalizer $textNormalizer
    ) {
    }

    public function load(string $basePath, array $files, ClassificationIndustryDataSet $dataSet): void
    {
        $system = 'UK_SIC';
        $version = '2026';

        $lastSectionCode = null;

        foreach ($this->reader->read($basePath . '/' . $files['classification']) as $row) {
            $code = (string)$row['code'];
            $levelName = (string)$row['level'];
            $parentCode = (string)($row['parent_code'] ?? '') ?: null;

            if ($levelName === 'section') {
                $lastSectionCode = $code;
            }

            // Repair division parent_code
            if ($levelName === 'division' && $parentCode !== null && is_numeric($parentCode)) {
                $parentCode = $lastSectionCode;
            }

            $depth = match ($levelName) {
                'section' => 1,
                'division' => 2,
                'group' => 3,
                'class' => 4,
                'subclass' => 5,
                default => 0,
            };

            $includes = $this->textNormalizer->normalize($row['includes'] ?? null);
            $includesAlso = $this->textNormalizer->normalize($row['includes_also'] ?? null);
            $excludes = $this->textNormalizer->normalize($row['excludes'] ?? null);

            $descriptionParts = [];
            if ($includes) { $descriptionParts[] = "Includes: " . $includes; }
            if ($includesAlso) { $descriptionParts[] = "Includes also: " . $includesAlso; }
            if ($excludes) { $descriptionParts[] = "Excludes: " . $excludes; }
            $description = $descriptionParts ? implode("\n\n", $descriptionParts) : null;

            $model = new ClassificationIndustryCode(
                $system,
                $version,
                $code,
                $code,
                [],
                $this->textNormalizer->normalize($row['title'] ?? '') ?? '',
                $description,
                $row['level'],
                $levelName,
                $depth,
                $parentCode,
                $levelName,
                (bool)($row['is_leaf'] ?? false),
                (bool)($row['is_selectable'] ?? true),
                true,
                null,
                null,
                null,
                $includes,
                $includesAlso,
                $excludes,
                null,
                [$row['source_file']]
            );
            $dataSet->addCode($model);
        }
    }
}
