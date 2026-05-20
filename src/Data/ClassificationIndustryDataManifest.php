<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Data;

final class ClassificationIndustryDataManifest
{
    public const SYSTEMS = [
        'NAICS' => [
            'name' => 'North American Industry Classification System',
            'region' => 'US',
            'description' => 'Industry classification system used by the United States, Canada, and Mexico.',
            'versions' => [
                '2022' => [
                    'label' => 'NAICS 2022',
                    'latest' => true,
                    'path' => 'naics/2022',
                    'files' => [
                        'structure' => '2022_NAICS_Structure.ndjson',
                        'descriptions' => '2022_NAICS_Descriptions.ndjson',
                        'index' => '2022_NAICS_Index_File.ndjson',
                        'six_digit_codes' => '6-digit_2022_Codes.ndjson',
                    ],
                ],
            ],
        ],
        'NACE' => [
            'name' => 'Statistical Classification of Economic Activities in the European Community',
            'region' => 'EU',
            'description' => 'The standard industrial classification system used in the European Union.',
            'versions' => [
                '2.1' => [
                    'label' => 'NACE Rev. 2.1',
                    'latest' => true,
                    'path' => 'nace/2.1',
                    'files' => [
                        'structure' => 'NACE_Rev2.1_Structure_Explanatory_Notes_EN.ndjson',
                        'headings_all_languages' => 'NACE_Rev2.1_Heading_All_Languages.ndjson',
                    ],
                ],
            ],
        ],
        'UK_SIC' => [
            'name' => 'UK Standard Industrial Classification of Economic Activities',
            'region' => 'UK',
            'description' => 'The system for classifying business activities in the United Kingdom.',
            'versions' => [
                '2026' => [
                    'label' => 'UK SIC 2026',
                    'latest' => true,
                    'path' => 'uk_sic/2026',
                    'files' => [
                        'classification' => 'sic2026classification.ndjson',
                    ],
                ],
            ],
        ],
        'ISIC' => [
            'name' => 'International Standard Industrial Classification of All Economic Activities',
            'region' => 'UN',
            'description' => 'The international standard for classification of all economic activities.',
            'versions' => [
                '5' => [
                    'label' => 'ISIC Rev. 5',
                    'latest' => true,
                    'path' => 'isic/5',
                    'files' => [
                        'structure' => 'ISIC_Rev_5_english_structure.ndjson',
                        'explanatory_notes' => 'ISIC5_Exp_Notes_11Mar2024.ndjson',
                    ],
                ],
            ],
        ],
    ];

    public static function getSystems(): array
    {
        return self::SYSTEMS;
    }
}
