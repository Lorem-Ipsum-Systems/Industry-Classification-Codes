<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Tests;

use LoremIpsum\IndustryClassificationCodes\Registry\ClassificationIndustryRegistry;
use PHPUnit\Framework\TestCase;

class SearchTest extends TestCase
{
    private ClassificationIndustryRegistry $registry;

    protected function setUp(): void
    {
        $dataPath = __DIR__ . '/Fixtures/data';
        $this->registry = ClassificationIndustryRegistry::fromDataPath($dataPath);
    }

    public function testExactCodeMatchHasHighestScore(): void
    {
        $results = $this->registry->search('NAICS', '2022', '111110');
        $this->assertNotEmpty($results);
        $this->assertEquals('111110', $results[0]->code->code);
        // The first result should be the exact match
    }

    public function testAliasMatch(): void
    {
        $results = $this->registry->search('ISIC', '5', 'A0111');
        $this->assertNotEmpty($results);
        $this->assertEquals('0111', $results[0]->code->code);
    }

    public function testPartialCodeMatch(): void
    {
        $results = $this->registry->search('NAICS', '2022', '11111');
        $codes = array_map(fn($r) => $r->code->code, $results);
        $this->assertContains('11111', $codes);
        $this->assertContains('111110', $codes);
    }

    public function testTitleMatch(): void
    {
        // 111110 is "Soybean Farming", 11111 is "Soybean Farming (5-digit)"
        $results = $this->registry->search('NAICS', '2022', 'Soybean Farming');
        $this->assertNotEmpty($results);
        $this->assertEquals('111110', $results[0]->code->code);
    }

    public function testSearchTermMatch(): void
    {
        // "Soybean farming" is a search term for 111110 in NAICS index file
        $results = $this->registry->search('NAICS', '2022', 'farming');
        $this->assertNotEmpty($results);
        $codes = array_map(fn($r) => $r->code->code, $results);
        $this->assertContains('111110', $codes);
    }

    public function testCaseInsensitivity(): void
    {
        $resultsLower = $this->registry->search('NAICS', '2022', 'soybean');
        $resultsUpper = $this->registry->search('NAICS', '2022', 'SOYBEAN');
        $this->assertEquals(count($resultsLower), count($resultsUpper));
    }

    public function testExplanatoryFieldsMatch(): void
    {
        // NACE class 01.11 has "Includes: This class includes..."
        $results = $this->registry->search('NACE', '2.1', 'class includes');
        $this->assertNotEmpty($results);
        $this->assertEquals('01.11', $results[0]->code->code);
    }
}
