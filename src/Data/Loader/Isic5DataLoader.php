<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Data\Loader;

use LoremIpsum\IndustryClassificationCodes\Data\ClassificationIndustryDataSet;
use LoremIpsum\IndustryClassificationCodes\Data\NdjsonReader;
use LoremIpsum\IndustryClassificationCodes\Data\TextNormalizer;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryCode;

final class Isic5DataLoader
{
    public function __construct(
        private NdjsonReader $reader,
        private TextNormalizer $textNormalizer
    ) {
    }

    public function load(string $basePath, array $files, ClassificationIndustryDataSet $dataSet): void
    {
        $system = 'ISIC';
        $version = '5';

        $rawCodes = [];

        // 1. Load basic structure
        foreach ($this->reader->read($basePath . '/' . $files['structure']) as $row) {
            $code = (string)$row['code'];
            $normalizedCode = $this->normalizeIsicCode($code);
            $parentCode = (string)($row['parent_code'] ?? '') ?: null;
            if ($parentCode !== null) {
                $parentCode = $this->normalizeIsicCode($parentCode);
            }

            $rawCodes[$normalizedCode] = [
                'code' => $code, // Canonical public code from structure
                'normalized_code' => $normalizedCode,
                'aliases' => [],
                'title' => $this->textNormalizer->normalize($row['title'] ?? '') ?? '',
                'level' => $row['level'],
                'parent_code' => $parentCode,
                'is_selectable' => true,
                'source_files' => [$row['source_file']],
                'introductory_text' => null,
                'includes' => null,
                'includes_also' => null,
                'excludes' => null,
            ];

            if ($code !== $normalizedCode) {
                $rawCodes[$normalizedCode]['aliases'][] = $code;
            }
        }

        // 2. Load and merge explanatory notes
        if (isset($files['explanatory_notes'])) {
            foreach ($this->reader->read($basePath . '/' . $files['explanatory_notes']) as $row) {
                $code = (string)$row['code'];
                $normalizedCode = $this->normalizeIsicCode($code);

                if (!isset($rawCodes[$normalizedCode])) {
                    // Structure defines the universe, but we might want to repair missing ones if needed.
                    // For now, follow the requirement that structure defines the valid codes.
                    continue;
                }

                $record = &$rawCodes[$normalizedCode];

                if ($code !== $record['code'] && !in_array($code, $record['aliases'], true)) {
                    $record['aliases'][] = $code;
                }

                // Repair/enrich from notes if needed
                $notesParentCode = (string)($row['parent_code'] ?? '') ?: null;
                if ($notesParentCode !== null) {
                    $notesParentCode = $this->normalizeIsicCode($notesParentCode);
                }

                if ($record['parent_code'] === null && $notesParentCode !== null) {
                    $record['parent_code'] = $notesParentCode;
                }

                if (isset($row['level']) && ($record['level'] === null || $record['level'] === '')) {
                    $record['level'] = $row['level'];
                }

                // If structure had missing info, use notes info
                if (!$record['title'] && isset($row['title'])) {
                    $record['title'] = $this->textNormalizer->normalize($row['title']);
                }

                $record['introductory_text'] = $this->textNormalizer->normalize($row['introductory_text'] ?? null);
                $record['includes'] = $this->textNormalizer->normalize($row['includes'] ?? null);
                $record['includes_also'] = $this->textNormalizer->normalize($row['includes_also'] ?? null);
                $record['excludes'] = $this->textNormalizer->normalize($row['excludes'] ?? null);

                if (!in_array($row['source_file'], $record['source_files'], true)) {
                    $record['source_files'][] = $row['source_file'];
                }
            }
        }

        // 3. Compute isLeaf
        $hasChildren = [];
        foreach ($rawCodes as $code => $data) {
            if ($data['parent_code'] !== null) {
                $hasChildren[$data['parent_code']] = true;
            }
        }

        // 4. Hydrate and add to DataSet
        foreach ($rawCodes as $normalizedCode => $data) {
            $levelName = $this->getIsicLevelName($data['level']);
            $depth = $this->getIsicDepth($levelName);

            $parentCode = $data['parent_code'];
            if ($parentCode !== null && !isset($rawCodes[$parentCode])) {
                $parentCode = null;
            }

            $descriptionParts = [];
            if ($data['introductory_text']) { $descriptionParts[] = $data['introductory_text']; }
            if ($data['includes']) { $descriptionParts[] = "Includes: " . $data['includes']; }
            if ($data['includes_also']) { $descriptionParts[] = "Includes also: " . $data['includes_also']; }
            if ($data['excludes']) { $descriptionParts[] = "Excludes: " . $data['excludes']; }
            $description = $descriptionParts ? implode("\n\n", $descriptionParts) : null;

            $model = new ClassificationIndustryCode(
                $system,
                $version,
                $data['code'],
                $data['normalized_code'],
                $data['aliases'],
                $data['title'],
                $description,
                $data['level'],
                $levelName,
                $depth,
                $parentCode,
                $levelName,
                !isset($hasChildren[$normalizedCode]),
                true, // isSelectable
                true, // isActive
                null,
                null,
                $data['introductory_text'],
                $data['includes'],
                $data['includes_also'],
                $data['excludes'],
                null,
                $data['source_files']
            );
            $dataSet->addCode($model);
        }
    }

    private function normalizeIsicCode(string $code): string
    {
        // Remove section prefix if it exists (e.g., A01 -> 01, A0111 -> 0111)
        if (preg_match('/^[A-Z](\d+)$/', $code, $matches)) {
            return $matches[1];
        }
        return $code;
    }

    private function getIsicLevelName(mixed $level): string
    {
        $level = (string)$level;
        return match ($level) {
            '1' => 'section',
            '2' => 'division',
            '3' => 'group',
            '4' => 'class',
            default => strtolower($level),
        };
    }

    private function getIsicDepth(string $levelName): int
    {
        return match ($levelName) {
            'section' => 1,
            'division' => 2,
            'group' => 3,
            'class' => 4,
            default => 0,
        };
    }
}
