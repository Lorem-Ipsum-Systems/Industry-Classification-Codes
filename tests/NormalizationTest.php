<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Tests;

use LoremIpsum\IndustryClassificationCodes\Normalization\ClassificationIndustryNormalizer;
use PHPUnit\Framework\TestCase;

class NormalizationTest extends TestCase
{
    private string $sourcePath;
    private string $normalizedPath;

    protected function setUp(): void
    {
        $this->sourcePath = __DIR__ . '/Fixtures/source';
        $this->normalizedPath = __DIR__ . '/Fixtures/normalized';

        if (!is_dir($this->normalizedPath)) {
            mkdir($this->normalizedPath, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->normalizedPath);
    }

    public function testNormalization(): void
    {
        $normalizer = new ClassificationIndustryNormalizer($this->sourcePath, $this->normalizedPath);
        $normalizer->normalize();

        $this->assertFileExists($this->normalizedPath . '/systems.ndjson');
        $this->assertFileExists($this->normalizedPath . '/versions.ndjson');
        $this->assertFileExists($this->normalizedPath . '/naics/2022/codes.ndjson');
        $this->assertFileExists($this->normalizedPath . '/nace/2.1/codes.ndjson');
        $this->assertFileExists($this->normalizedPath . '/uk_sic/2026/codes.ndjson');
        $this->assertFileExists($this->normalizedPath . '/isic/5/codes.ndjson');

        // NAICS 2022
        $naicsCodes = $this->readNormalized($this->normalizedPath . '/naics/2022/codes.ndjson');
        $this->assertCount(5, $naicsCodes); // 11, 111, 1111, 11111, 111110
        $this->assertEquals('Soybean Farming', $naicsCodes['111110']['title']);
        $this->assertEquals('11111', $naicsCodes['111110']['parent_code']);
        $this->assertStringContainsString('comprises establishments primarily engaged in growing soybeans', $naicsCodes['111110']['description']);
        $this->assertEquals('national_industry', $naicsCodes['111110']['level_name']);
        $this->assertEquals(5, $naicsCodes['111110']['depth']);

        $naicsSearch = $this->readNormalized($this->normalizedPath . '/naics/2022/search_terms.ndjson', false);
        $this->assertCount(1, $naicsSearch);
        $this->assertEquals('111110', $naicsSearch[0]['code']);
        $this->assertEquals('Soybean farming', $naicsSearch[0]['term']);

        // NACE 2.1
        $naceCodes = $this->readNormalized($this->normalizedPath . '/nace/2.1/codes.ndjson');
        $this->assertCount(4, $naceCodes);
        $this->assertFalse($naceCodes['A']['is_leaf']);
        $this->assertTrue($naceCodes['01.11']['is_leaf']);
        $this->assertEquals('section', $naceCodes['A']['level_name']);
        $this->assertEquals(1, $naceCodes['A']['depth']);

        $naceTrans = $this->readNormalized($this->normalizedPath . '/nace/2.1/translations.ndjson', false);
        $this->assertCount(3, $naceTrans);
        $this->assertEquals('fr', $naceTrans[0]['locale']);

        // UK SIC 2026
        $ukSicCodes = $this->readNormalized($this->normalizedPath . '/uk_sic/2026/codes.ndjson');
        $this->assertEquals('A', $ukSicCodes['01']['parent_code']); // Repaired from "0"
        $this->assertTrue($ukSicCodes['01.11']['is_leaf']);
        $this->assertEquals('division', $ukSicCodes['01']['level_name']);

        // ISIC 5
        $isicCodes = $this->readNormalized($this->normalizedPath . '/isic/5/codes.ndjson');
        $this->assertEquals('0111', $isicCodes['0111']['code']);
        $this->assertContains('A0111', $isicCodes['0111']['aliases']);
        $this->assertStringContainsString('This class includes', $isicCodes['0111']['description']);
    }

    private function readNormalized(string $path, bool $keyed = true): array
    {
        $lines = file($path);
        $data = [];
        foreach ($lines as $line) {
            $row = json_decode($line, true);
            if ($row === null) continue;
            if ($keyed) {
                $data[$row['code']] = $row;
            } else {
                $data[] = $row;
            }
        }
        return $data;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$path/$file")) ? $this->removeDirectory("$path/$file") : unlink("$path/$file");
        }
        rmdir($path);
    }
}
