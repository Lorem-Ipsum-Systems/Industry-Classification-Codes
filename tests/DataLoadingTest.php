<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Tests;

use LoremIpsum\IndustryClassificationCodes\Data\ShippedClassificationIndustryDataLoader;
use LoremIpsum\IndustryClassificationCodes\Data\ShippedClassificationIndustryDataValidator;
use LoremIpsum\IndustryClassificationCodes\Registry\ClassificationIndustryRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DataLoadingTest extends TestCase
{
    private string $tempDataDir;

    protected function setUp(): void
    {
        $this->tempDataDir = sys_get_temp_dir() . '/industry_classification_test_' . uniqid();
        mkdir($this->tempDataDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDataDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->removeDirectory("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function testDefaultDataPathResolves(): void
    {
        // This test assumes we are in the vendor dir or at the root of the project.
        // fromDefaultData uses __DIR__ relative path.
        $registry = ClassificationIndustryRegistry::fromDefaultData();
        $this->assertNotEmpty($registry->systems());
    }

    public function testMissingShippedFileProducesError(): void
    {
        // Create an incomplete structure
        $naicsDir = $this->tempDataDir . '/naics/2022';
        mkdir($naicsDir, 0777, true);
        // missing structure file

        $loader = new ShippedClassificationIndustryDataLoader($this->tempDataDir);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found');
        $loader->load();
    }

    public function testMalformedNdjsonProducesError(): void
    {
        $naicsDir = $this->tempDataDir . '/naics/2022';
        mkdir($naicsDir, 0777, true);
        file_put_contents($naicsDir . '/2022_NAICS_Structure.ndjson', '{"invalid": json}');

        $validator = new ShippedClassificationIndustryDataValidator();
        $validator->validate($this->tempDataDir);

        $errors = $validator->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Invalid JSON', $errors[0]);
        $this->assertStringContainsString('line 1', $errors[0]);
    }

    public function testDuplicateCanonicalCodesAreDetected(): void
    {
        $naicsDir = $this->tempDataDir . '/naics/2022';
        mkdir($naicsDir, 0777, true);
        $content = json_encode([
            'system' => 'NAICS',
            'version' => '2022',
            'code' => '11',
            'title' => 'Title 1',
            'level' => 1,
            'source_file' => 'test.xlsx'
        ]) . "\n" . json_encode([
            'system' => 'NAICS',
            'version' => '2022',
            'code' => '11',
            'title' => 'Title 2',
            'level' => 1,
            'source_file' => 'test.xlsx'
        ]);
        file_put_contents($naicsDir . '/2022_NAICS_Structure.ndjson', $content);

        $validator = new ShippedClassificationIndustryDataValidator();
        $validator->validate($this->tempDataDir);

        $errors = $validator->getErrors();
        $this->assertContains('Duplicate canonical code "11" in ' . $naicsDir . '/2022_NAICS_Structure.ndjson line 2', $errors);
    }
}
