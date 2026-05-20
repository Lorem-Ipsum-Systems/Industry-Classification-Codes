<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Tests;

use LoremIpsum\IndustryClassificationCodes\ClassificationIndustryRegistry;
use LoremIpsum\IndustryClassificationCodes\Normalization\ClassificationIndustryNormalizer;
use LoremIpsum\IndustryClassificationCodes\Repositories\NdjsonClassificationIndustryRepository;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
{
    private string $sourcePath;
    private string $normalizedPath;
    private ClassificationIndustryRegistry $registry;

    protected function setUp(): void
    {
        $this->sourcePath = __DIR__ . '/Fixtures/source';
        $this->normalizedPath = __DIR__ . '/Fixtures/normalized_registry';

        if (!is_dir($this->normalizedPath)) {
            mkdir($this->normalizedPath, 0777, true);
        }

        $normalizer = new ClassificationIndustryNormalizer($this->sourcePath, $this->normalizedPath);
        $normalizer->normalize();

        $repository = new NdjsonClassificationIndustryRepository($this->normalizedPath);
        $this->registry = new ClassificationIndustryRegistry($repository);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->normalizedPath);
    }

    public function testGetSystems(): void
    {
        $systems = $this->registry->getSystems();
        $this->assertCount(4, $systems);
        $this->assertEquals('NAICS', $systems[0]->key);
        $this->assertEquals('US', $systems[0]->region);
    }

    public function testFindCode(): void
    {
        $code = $this->registry->findCode('NAICS', '2022', '11');
        $this->assertNotNull($code);
        $this->assertEquals('Agriculture, Forestry, Fishing and Hunting', $code->title);
        $this->assertStringContainsString('The Agriculture, Forestry, Fishing and Hunting sector', $code->description);
    }

    public function testFindCodeWithAlias(): void
    {
        // ISIC code A0111 is an alias for 0111
        $code = $this->registry->findCode('ISIC', '5', 'A0111');
        $this->assertNotNull($code);
        $this->assertEquals('0111', $code->code);
        $this->assertEquals('Growing of cereals (except rice), leguminous crops and oil seeds', $code->title);
    }

    public function testGetChildren(): void
    {
        $children = $this->registry->getChildren('NAICS', '2022', '11');
        $this->assertCount(1, $children);
        $this->assertEquals('111', $children[0]->code);
    }

    public function testValidateCode(): void
    {
        $this->assertTrue($this->registry->validateCode('NAICS', '2022', '11'));
        $this->assertFalse($this->registry->validateCode('NAICS', '2022', '99'));
    }

    public function testGetTranslations(): void
    {
        $translations = $this->registry->getTranslations('NACE', '2.1', 'A');
        $this->assertCount(2, $translations);
        $this->assertEquals('de', $translations[0]->locale);
    }

    public function testSearch(): void
    {
        $results = $this->registry->search('NAICS', '2022', 'Soybean');
        $this->assertCount(1, $results);
        $this->assertEquals('111110', $results[0]->code->code);
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
