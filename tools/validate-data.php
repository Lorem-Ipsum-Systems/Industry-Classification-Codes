#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LoremIpsum\IndustryClassificationCodes\Data\ShippedClassificationIndustryDataValidator;

$dataRootPath = __DIR__ . '/../data';

echo "Validating industry classification data...\n";

$validator = new ShippedClassificationIndustryDataValidator();
$validator->validate($dataRootPath);

foreach ($validator->getWarnings() as $warning) {
    echo "[WARNING] $warning\n";
}

if (!$validator->isValid()) {
    foreach ($validator->getErrors() as $error) {
        echo "[ERROR] $error\n";
    }
    echo "\nValidation failed!\n";
    exit(1);
}

echo "Validation successful!\n";
exit(0);
