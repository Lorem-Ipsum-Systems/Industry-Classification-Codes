<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Tests;

use LoremIpsum\IndustryClassificationCodes\ClassificationIndustryRegistry;
use LoremIpsum\IndustryClassificationCodes\Data\ShippedClassificationIndustryDataLoader;
use LoremIpsum\IndustryClassificationCodes\Repositories\InMemoryClassificationIndustryRepository;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
{
    private ClassificationIndustryRegistry $registry;

    protected function setUp(): void
    {
        $dataPath = __DIR__ . '/Fixtures/data';
        $loader = new ShippedClassificationIndustryDataLoader($dataPath);
        $dataSet = $loader->load();

        $repository = new InMemoryClassificationIndustryRepository($dataSet);
        $this->registry = new ClassificationIndustryRegistry($repository);
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
        $locales = array_map(fn($t) => $t->locale, $translations);
        $this->assertContains('de', $locales);
        $this->assertContains('fr', $locales);
    }

    public function testSearch(): void
    {
        $results = $this->registry->search('NAICS', '2022', 'Soybean');
        // Both "Soybean Farming (5-digit)" and "Soybean Farming" (6-digit) are in fixtures
        $this->assertGreaterThanOrEqual(1, count($results));
        $codes = array_map(fn($r) => $r->code->code, $results);
        $this->assertContains('111110', $codes);
    }
}
