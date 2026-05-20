<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Tests;

use PHPUnit\Framework\TestCase;

class FoundationTest extends TestCase
{
    private array $composerJson;

    protected function setUp(): void
    {
        $this->composerJson = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);
    }

    public function testPackageName(): void
    {
        $this->assertEquals('loremipsum-system/industry-classification-codes', $this->composerJson['name']);
    }

    public function testPhpRequirement(): void
    {
        $this->assertArrayHasKey('php', $this->composerJson['require']);
        $this->assertStringContainsString('8.2', $this->composerJson['require']['php']);
    }

    public function testAutoloadNamespace(): void
    {
        $this->assertArrayHasKey('LoremIpsum\\IndustryClassificationCodes\\', $this->composerJson['autoload']['psr-4']);
        $this->assertEquals('src/', $this->composerJson['autoload']['psr-4']['LoremIpsum\\IndustryClassificationCodes\\']);
    }

    public function testNoForbiddenDependencies(): void
    {
        $forbidden = [
            'phpoffice/phpspreadsheet',
            'symfony/serializer',
            'symfony/framework-bundle',
            'laravel/framework',
            'doctrine/orm',
            'illuminate/database',
        ];

        $require = $this->composerJson['require'] ?? [];
        foreach ($forbidden as $package) {
            $this->assertArrayNotHasKey($package, $require, "Package $package should not be a runtime dependency.");
        }
    }
}
