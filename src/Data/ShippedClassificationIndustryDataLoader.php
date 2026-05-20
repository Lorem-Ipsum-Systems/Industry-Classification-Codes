<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Data;

use LoremIpsum\IndustryClassificationCodes\Data\Loader\Isic5DataLoader;
use LoremIpsum\IndustryClassificationCodes\Data\Loader\Nace21DataLoader;
use LoremIpsum\IndustryClassificationCodes\Data\Loader\Naics2022DataLoader;
use LoremIpsum\IndustryClassificationCodes\Data\Loader\UkSic2026DataLoader;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustrySystem;
use LoremIpsum\IndustryClassificationCodes\Model\ClassificationIndustryVersion;
use RuntimeException;

final class ShippedClassificationIndustryDataLoader
{
    private NdjsonReader $reader;
    private TextNormalizer $textNormalizer;

    public function __construct(
        private string $dataRootPath
    ) {
        $this->reader = new NdjsonReader();
        $this->textNormalizer = new TextNormalizer();
    }

    public function load(): ClassificationIndustryDataSet
    {
        $dataSet = new ClassificationIndustryDataSet();
        $manifest = ClassificationIndustryDataManifest::getSystems();

        foreach ($manifest as $systemKey => $systemData) {
            $dataSet->addSystem(new ClassificationIndustrySystem(
                $systemKey,
                $systemData['name'],
                $systemData['region'],
                $systemData['description']
            ));

            foreach ($systemData['versions'] as $versionKey => $versionData) {
                $versionKey = (string)$versionKey;
                $dataSet->addVersion(new ClassificationIndustryVersion(
                    $systemKey,
                    $versionKey,
                    $versionData['label'],
                    $versionData['latest'],
                    $versionData['path']
                ));

                $this->loadVersionData($systemKey, $versionKey, $versionData, $dataSet);
            }
        }

        return $dataSet;
    }

    private function loadVersionData(
        string $systemKey,
        string $versionKey,
        array $versionData,
        ClassificationIndustryDataSet $dataSet
    ): void {
        $basePath = rtrim($this->dataRootPath, '/') . '/' . ltrim($versionData['path'], '/');
        $files = $versionData['files'];

        if ($systemKey === 'NAICS' && $versionKey === '2022') {
            (new Naics2022DataLoader($this->reader, $this->textNormalizer))->load($basePath, $files, $dataSet);
        } elseif ($systemKey === 'NACE' && $versionKey === '2.1') {
            (new Nace21DataLoader($this->reader, $this->textNormalizer))->load($basePath, $files, $dataSet);
        } elseif ($systemKey === 'UK_SIC' && $versionKey === '2026') {
            (new UkSic2026DataLoader($this->reader, $this->textNormalizer))->load($basePath, $files, $dataSet);
        } elseif ($systemKey === 'ISIC' && $versionKey === '5') {
            (new Isic5DataLoader($this->reader, $this->textNormalizer))->load($basePath, $files, $dataSet);
        } else {
            throw new RuntimeException(sprintf('No loader found for %s %s', $systemKey, $versionKey));
        }
    }
}
