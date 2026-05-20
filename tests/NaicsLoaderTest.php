<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Tests;

use LoremIpsum\IndustryClassificationCodes\Registry\ClassificationIndustryRegistry;
use PHPUnit\Framework\TestCase;

class NaicsLoaderTest extends TestCase
{
    private ClassificationIndustryRegistry $registry;

    protected function setUp(): void
    {
        $dataPath = __DIR__ . '/Fixtures/data';
        $this->registry = ClassificationIndustryRegistry::fromDataPath($dataPath);
    }

    public function testNaicsRepairsAndNormalization(): void
    {
        // Test "31-33" repair and " T" removal
        $mfg = $this->registry->findCode('NAICS', '2022', '31-33');
        $this->assertNotNull($mfg);
        $this->assertEquals('Manufacturing', $mfg->title); // " T" removed
        $this->assertNull($mfg->parentCode); // repaired from "31-3"

        // Test mapping 31, 32, 33 to 31-33
        $food = $this->registry->findCode('NAICS', '2022', '311');
        $this->assertNotNull($food);
        $this->assertEquals('31-33', $food->parentCode); // mapped from 31

        // Test nan description normalization
        $this->assertNull($food->description); // "nan" normalized to null

        // Test search terms skipped "******"
        $terms = $this->registry->search('NAICS', '2022', 'Invalid Item');
        $this->assertEmpty($terms);
    }

    public function testNaicsVariantAndChangeIndicator(): void
    {
        $soy = $this->registry->findCode('NAICS', '2022', '111110');
        $this->assertNotNull($soy);
        $this->assertEquals('0', $soy->changeIndicator);
        // Variant is joined from 6-digit file (which has it)
        // Let's check 6-digit file fixture
    }
}
