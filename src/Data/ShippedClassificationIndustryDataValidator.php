<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Data;

use RuntimeException;

final class ShippedClassificationIndustryDataValidator
{
    /** @var string[] */
    private array $errors = [];

    /** @var string[] */
    private array $warnings = [];

    /** @var array<string, array<string, array<string, bool>>> */
    private array $seenCodes = [];

    /** @var array<string, array<string, array<string, array<string, bool>>>> */
    private array $seenTranslations = [];

    /** @var array<string, array<string, array<string, string>>> */
    private array $seenAliases = [];

    public function validate(string $dataRootPath): void
    {
        $this->errors = [];
        $this->warnings = [];
        $this->seenCodes = [];
        $this->seenTranslations = [];
        $this->seenAliases = [];

        $manifest = ClassificationIndustryDataManifest::getSystems();
        $reader = new NdjsonReader();

        foreach ($manifest as $systemKey => $systemData) {
            foreach ($systemData['versions'] as $versionKey => $versionData) {
                $versionKey = (string)$versionKey;
                $basePath = rtrim($dataRootPath, '/') . '/' . ltrim($versionData['path'], '/');

                // First pass: collect valid codes from structure files
                $universe = [];
                $structureRole = ($systemKey === 'UK_SIC') ? 'classification' : 'structure';
                $structureFile = $versionData['files'][$structureRole] ?? null;

                if ($structureFile) {
                    $filePath = $basePath . '/' . $structureFile;
                    if (file_exists($filePath)) {
                        try {
                            foreach ($reader->read($filePath) as $row) {
                                if (isset($row['code'])) {
                                    $universe[(string)$row['code']] = true;
                                }
                            }
                        } catch (RuntimeException) {
                            // Will be caught in the second pass
                        }
                    }
                }

                foreach ($versionData['files'] as $fileRole => $fileName) {
                    $filePath = $basePath . '/' . $fileName;
                    if (!file_exists($filePath)) {
                        $this->errors[] = sprintf('Missing expected file: %s', $filePath);
                        continue;
                    }

                    try {
                        foreach ($reader->read($filePath) as $line => $row) {
                            $this->validateRow($row, $systemKey, $versionKey, $fileRole, $filePath, $line, $universe);
                        }
                    } catch (RuntimeException $e) {
                        $this->errors[] = $e->getMessage();
                    }
                }
            }
        }

        // Perform cross-file validation by loading into a DataSet
        try {
            $loader = new ShippedClassificationIndustryDataLoader($dataRootPath);
            $dataSet = $loader->load();
            $this->validateDataSet($dataSet);
        } catch (RuntimeException $e) {
            $this->errors[] = sprintf('Loader failed: %s', $e->getMessage());
        }
    }

    private function validateRow(array $row, string $expectedSystem, string $expectedVersion, string $fileRole, string $filePath, int $line, array $universe): void
    {
        // Basic field checks
        if (!isset($row['system'])) {
            $this->errors[] = sprintf('Missing "system" in %s line %d', $filePath, $line);
        } elseif ($row['system'] !== $expectedSystem) {
            $this->errors[] = sprintf('Unexpected system "%s" in %s line %d (expected "%s")', $row['system'], $filePath, $line, $expectedSystem);
        }

        if (!isset($row['version'])) {
            $this->errors[] = sprintf('Missing "version" in %s line %d', $filePath, $line);
        } elseif ((string)$row['version'] !== $expectedVersion) {
            $this->errors[] = sprintf('Unexpected version "%s" in %s line %d (expected "%s")', $row['version'], $filePath, $line, $expectedSystem === 'NACE' ? '2.1' : $expectedVersion);
        }

        if ($expectedSystem === 'NAICS' && $fileRole === 'index' && ($row['code'] ?? '') === '******') {
            return;
        }

        if (!isset($row['code'])) {
            $this->errors[] = sprintf('Missing "code" in %s line %d', $filePath, $line);
            return;
        }

        $code = (string)$row['code'];

        // Duplicate detection for primary structure files
        if (in_array($fileRole, ['structure', 'classification'], true)) {
            if (isset($this->seenCodes[$expectedSystem][$expectedVersion][$code])) {
                $this->errors[] = sprintf('Duplicate canonical code "%s" in %s line %d', $code, $filePath, $line);
            }
            $this->seenCodes[$expectedSystem][$expectedVersion][$code] = true;
        } else {
            // Check for unresolved enrichment rows
            // For ISIC, we need to be careful because codes might be prefixed aliases in explanatory notes
            $checkCode = $code;
            if ($expectedSystem === 'ISIC' && $fileRole === 'explanatory_notes') {
                $checkCode = $this->normalizeIsicCode($code);
            }

            if (!isset($universe[$checkCode])) {
                if ($expectedSystem === 'NAICS' && $fileRole === 'index') {
                    $this->errors[] = sprintf('Unknown NAICS index code "%s" in %s line %d', $code, $filePath, $line);
                } else {
                    $this->errors[] = sprintf('Unresolved enrichment row for code "%s" in %s line %d', $code, $filePath, $line);
                }
            }
        }

        // Translation duplicate detection
        if ($fileRole === 'headings_all_languages') {
            $locale = $row['locale'] ?? 'unknown';
            if (isset($this->seenTranslations[$expectedSystem][$expectedVersion][$code][$locale])) {
                $this->errors[] = sprintf('Duplicate translation for code "%s" locale "%s" in %s line %d', $code, $locale, $filePath, $line);
            }
            $this->seenTranslations[$expectedSystem][$expectedVersion][$code][$locale] = true;
        }
    }

    private function normalizeIsicCode(string $code): string
    {
        if (preg_match('/^[A-Z](\d+)$/', $code, $matches)) {
            return $matches[1];
        }
        return $code;
    }

    private function validateDataSet(ClassificationIndustryDataSet $dataSet): void
    {
        foreach ($dataSet->codes as $system => $versions) {
            foreach ($versions as $version => $codes) {
                foreach ($codes as $code => $model) {
                    if ($model->parentCode !== null) {
                        if (!isset($codes[$model->parentCode])) {
                            $this->errors[] = sprintf('[%s %s] Code %s has non-existent parent %s', $system, $version, $code, $model->parentCode);
                        }
                    }

                    // Check for duplicate aliases pointing to different codes
                    foreach ($model->aliases as $alias) {
                        if (isset($this->seenAliases[$system][$version][$alias]) && $this->seenAliases[$system][$version][$alias] !== $code) {
                            $this->errors[] = sprintf('[%s %s] Alias %s points to multiple codes: %s and %s', $system, $version, $alias, $this->seenAliases[$system][$version][$alias], $code);
                        }
                        $this->seenAliases[$system][$version][$alias] = $code;
                    }
                }
            }
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }
}
