#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LoremIpsum\IndustryClassificationCodes\Normalization\ClassificationIndustryNormalizer;

$sourcePath = __DIR__ . '/../data/source';
$normalizedPath = __DIR__ . '/../data/normalized';

$normalizer = new ClassificationIndustryNormalizer($sourcePath, $normalizedPath);

echo "Starting normalization...\n";
$normalizer->normalize();
echo "Normalization complete.\n";
