<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Tests;

use LoremIpsum\IndustryClassificationCodes\Registry\ClassificationIndustryRegistry;
use PHPUnit\Framework\TestCase;

class UkSicLoaderTest extends TestCase
{
    private ClassificationIndustryRegistry $registry;

    protected function setUp(): void
    {
        $dataPath = __DIR__ . '/Fixtures/data';
        $this->registry = ClassificationIndustryRegistry::fromDataPath($dataPath);
    }

    public function testUkSicParentRepair(): void
    {
        // "01" has parent_code "0" in fixture, should be repaired to "A" (nearest preceding section)
        $code = $this->registry->findCode('UK_SIC', '2026', '01');
        $this->assertNotNull($code);
        $this->assertEquals('A', $code->parentCode);
    }
}
