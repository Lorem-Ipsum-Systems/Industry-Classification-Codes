<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Tests;

use LoremIpsum\IndustryClassificationCodes\Registry\ClassificationIndustryRegistry;
use PHPUnit\Framework\TestCase;

class NaceLoaderTest extends TestCase
{
    private ClassificationIndustryRegistry $registry;

    protected function setUp(): void
    {
        $dataPath = __DIR__ . '/Fixtures/data';
        $this->registry = ClassificationIndustryRegistry::fromDataPath($dataPath);
    }

    public function testNaceDescriptionBuilding(): void
    {
        $code = $this->registry->findCode('NACE', '2.1', 'A');
        $this->assertNotNull($code);
        $this->assertStringContainsString('Includes: This section includes...', $code->description);
    }

    public function testNaceTranslations(): void
    {
        $translations = $this->registry->translationsFor('NACE', '2.1', 'A');
        $this->assertCount(2, $translations);

        $de = $this->registry->translationFor('NACE', '2.1', 'A', 'de');
        $this->assertEquals('LAND- UND FORSTWIRTSCHAFT, FISCHEREI', $de->title);
    }
}
