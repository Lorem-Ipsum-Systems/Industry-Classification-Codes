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

        $rawCodes = [];
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

            $rawCodes[$code] = [
                'code' => $code,
                'title' => $this->textNormalizer->normalize($row['title'] ?? '') ?? '',
                'description' => $description,
                'level' => $row['level'],
                'level_name' => $levelName,
                'depth' => $depth,
                'parent_code' => $parentCode,
                'includes' => $includes,
                'includes_also' => $includesAlso,
                'excludes' => $excludes,
                'source_file' => $row['source_file'],
            ];
        }

        // Compute isLeaf
        $hasChildren = [];
        foreach ($rawCodes as $code => $data) {
            if ($data['parent_code'] !== null) {
                $hasChildren[$data['parent_code']] = true;
            }
        }

        // Hydrate and add to DataSet
        foreach ($rawCodes as $code => $data) {
            $code = (string)$code;
            $parentCode = $data['parent_code'];
            if ($parentCode !== null && !isset($rawCodes[$parentCode])) {
                $parentCode = null;
            }

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
                $data['depth'],
                $parentCode,
                $data['level_name'],
                !isset($hasChildren[$code]),
                true, // isSelectable
                true, // isActive
                null,
                null,
                null,
                $data['includes'],
                $data['includes_also'],
                $data['excludes'],
                null,
                [$data['source_file']]
            );
            $dataSet->addCode($model);
        }
    }
}
