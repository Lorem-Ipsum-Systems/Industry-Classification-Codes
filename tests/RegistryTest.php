<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Tests;

use LoremIpsum\IndustryClassificationCodes\Registry\ClassificationIndustryRegistry;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
{
    private ClassificationIndustryRegistry $registry;

    protected function setUp(): void
    {
        $dataPath = __DIR__ . '/Fixtures/data';
        $this->registry = ClassificationIndustryRegistry::fromDataPath($dataPath);
    }

    public function testSystems(): void
    {
        $systems = $this->registry->systems();
        $this->assertCount(4, $systems);
        $this->assertEquals('NAICS', $systems[0]->key);
        $this->assertEquals('US', $systems[0]->region);
    }

    public function testVersions(): void
    {
        $versions = $this->registry->versions('NAICS');
        $this->assertCount(1, $versions);
        $this->assertEquals('2022', $versions[0]->version);
    }

    public function testLatestVersion(): void
    {
        $version = $this->registry->latestVersion('NAICS');
        $this->assertNotNull($version);
        $this->assertEquals('2022', $version->version);
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

    public function testIsValidCode(): void
    {
        $this->assertTrue($this->registry->isValidCode('NAICS', '2022', '11'));
        $this->assertFalse($this->registry->isValidCode('NAICS', '2022', '99'));
        $this->assertTrue($this->registry->isValidCode('ISIC', '5', 'A0111'));
    }

    public function testChildrenOf(): void
    {
        // Root children
        $root = $this->registry->childrenOf('NAICS', '2022', null);
        $this->assertNotEmpty($root);
        $this->assertEquals('11', $root[0]->code);

        // Specific child
        $children = $this->registry->childrenOf('NAICS', '2022', '11');
        $this->assertCount(1, $children);
        $this->assertEquals('111', $children[0]->code);
    }

    public function testParentOf(): void
    {
        $parent = $this->registry->parentOf('NAICS', '2022', '111');
        $this->assertNotNull($parent);
        $this->assertEquals('11', $parent->code);
    }

    public function testAncestorsOf(): void
    {
        $ancestors = $this->registry->ancestorsOf('NAICS', '2022', '111110');
        // 111110 -> 11111 -> 1111 -> 111 -> 11
        $this->assertCount(4, $ancestors);
        $this->assertEquals('11111', $ancestors[0]->code);
        $this->assertEquals('11', end($ancestors)->code);
    }

    public function testDescendantsOf(): void
    {
        $descendants = $this->registry->descendantsOf('NAICS', '2022', '111');
        // 111 -> 1111 -> 11111 -> 111110
        $this->assertCount(3, $descendants);
    }

    public function testLeafCodes(): void
    {
        $leaves = $this->registry->leafCodes('NAICS', '2022');
        $this->assertNotEmpty($leaves);
        foreach ($leaves as $leaf) {
            $this->assertTrue($leaf->isLeaf);
        }
    }

    public function testTranslationsFor(): void
    {
        $translations = $this->registry->translationsFor('NACE', '2.1', 'A');
        $this->assertCount(2, $translations);
        $locales = array_map(fn($t) => $t->locale, $translations);
        $this->assertContains('de', $locales);
        $this->assertContains('fr', $locales);
    }

    public function testTranslationFor(): void
    {
        $translation = $this->registry->translationFor('NACE', '2.1', 'A', 'de');
        $this->assertNotNull($translation);
        $this->assertEquals('LAND- UND FORSTWIRTSCHAFT, FISCHEREI', $translation->title);
    }

    public function testSearch(): void
    {
        $results = $this->registry->search('NAICS', '2022', 'Soybean');
        $this->assertNotEmpty($results);
        $codes = array_map(fn($r) => $r->code->code, $results);
        $this->assertContains('111110', $codes);
        $this->assertContains('11111', $codes);
    }
}
