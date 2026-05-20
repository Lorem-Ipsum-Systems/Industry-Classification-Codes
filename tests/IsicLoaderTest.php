<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Tests;

use LoremIpsum\IndustryClassificationCodes\Registry\ClassificationIndustryRegistry;
use PHPUnit\Framework\TestCase;

class IsicLoaderTest extends TestCase
{
    private ClassificationIndustryRegistry $registry;

    protected function setUp(): void
    {
        $dataPath = __DIR__ . '/Fixtures/data';
        $this->registry = ClassificationIndustryRegistry::fromDataPath($dataPath);
    }

    public function testIsicAliasAndNormalization(): void
    {
        // 0111 is canonical, A0111 is alias
        $code = $this->registry->findCode('ISIC', '5', 'A0111');
        $this->assertNotNull($code);
        $this->assertEquals('0111', $code->code);
        $this->assertContains('A0111', $code->aliases);

        // Test _x000D_ normalization
        $this->assertStringContainsString("This class includes\nmultiline", $code->includes);
    }

    public function testIsicParentRepair(): void
    {
        // A0111 in notes has parent A011.
        // A011 in structure has code 011.
        // So 0111 should have parent 011.
        $code = $this->registry->findCode('ISIC', '5', '0111');
        $this->assertEquals('011', $code->parentCode);
    }
}
