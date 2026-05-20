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

    public function validate(string $dataRootPath): void
    {
        $this->errors = [];
        $this->warnings = [];

        $manifest = ClassificationIndustryDataManifest::getSystems();
        $reader = new NdjsonReader();

        foreach ($manifest as $systemKey => $systemData) {
            foreach ($systemData['versions'] as $versionKey => $versionData) {
                $versionKey = (string)$versionKey;
                $basePath = rtrim($dataRootPath, '/') . '/' . ltrim($versionData['path'], '/');

                foreach ($versionData['files'] as $fileRole => $fileName) {
                    $filePath = $basePath . '/' . $fileName;
                    if (!file_exists($filePath)) {
                        $this->errors[] = sprintf('Missing expected file: %s', $filePath);
                        continue;
                    }

                    try {
                        foreach ($reader->read($filePath) as $line => $row) {
                            $this->validateRow($row, $systemKey, $versionKey, $fileRole, $filePath, $line);
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

    private function validateRow(array $row, string $expectedSystem, string $expectedVersion, string $fileRole, string $filePath, int $line): void
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
            $this->errors[] = sprintf('Unexpected version "%s" in %s line %d (expected "%s")', $row['version'], $filePath, $line, $expectedVersion);
        }

        if ($expectedSystem === 'NAICS' && $fileRole === 'index' && ($row['code'] ?? '') === '******') {
            // This is skipped by loader, but we can warn if needed. Requirements say skip or warn.
            return;
        }

        if (!isset($row['code'])) {
            $this->errors[] = sprintf('Missing "code" in %s line %d', $filePath, $line);
        }
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
