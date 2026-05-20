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

        // Check content of one file
        $naicsCodes = file($this->normalizedPath . '/naics/2022/codes.ndjson');
        $this->assertCount(4, $naicsCodes);
        $firstCode = json_decode($naicsCodes[0], true);
        $this->assertEquals('11', $firstCode['code']);
        $this->assertEquals('Agriculture, Forestry, Fishing and Hunting', $firstCode['name']);
        $this->assertNull($firstCode['parent_code']);
        
        $secondCode = json_decode($naicsCodes[1], true);
        $this->assertEquals('111', $secondCode['code']);
        $this->assertEquals('11', $secondCode['parent_code']);
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
