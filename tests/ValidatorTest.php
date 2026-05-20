<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Tests;

use LoremIpsum\IndustryClassificationCodes\Data\ShippedClassificationIndustryDataValidator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    public function testValidateFixtures(): void
    {
        $dataPath = __DIR__ . '/Fixtures/data';
        $validator = new ShippedClassificationIndustryDataValidator();
        $validator->validate($dataPath);

        $this->assertTrue($validator->isValid(), implode("\n", $validator->getErrors()));
    }
}
