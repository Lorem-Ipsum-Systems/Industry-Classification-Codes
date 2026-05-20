# Industry Classification Codes

A framework-agnostic PHP package for industry classification reference data, lookup, validation, hierarchy browsing, and search.

Supports:
- NAICS 2022
- NACE Rev. 2.1
- UK SIC 2026
- ISIC Rev. 5

## Installation

```bash
composer require loremipsum-system/industry-classification-codes
```

## Data Setup

This package uses NDJSON files for reference data. You must provide the source files in `data/source/`.

Expected structure:

```text
data/source/
  naics/2022/
    2022_NAICS_Structure.ndjson
    2022_NAICS_Descriptions.ndjson
    2022_NAICS_Index_File.ndjson
    6-digit_2022_Codes.ndjson
  nace/2.1/
    NACE_Rev2.1_Structure_Explanatory_Notes_EN.ndjson
    NACE_Rev2.1_Heading_All_Languages.ndjson
  uk_sic/2026/
    sic2026classification.ndjson
  isic/5/
    ISIC_Rev_5_english_structure.ndjson
    ISIC5_Exp_Notes_11Mar2024.ndjson
```

After placing the files, run the normalization tool:

```bash
php tools/normalize.php
```

This will generate optimized runtime files in `data/normalized/`.

## Usage

### Initialize the Registry

```php
use LoremIpsum\IndustryClassificationCodes\ClassificationIndustryRegistry;
use LoremIpsum\IndustryClassificationCodes\Repositories\NdjsonClassificationIndustryRepository;

$repository = new NdjsonClassificationIndustryRepository(__DIR__ . '/data/normalized');
$registry = new ClassificationIndustryRegistry($repository);
```

### Lookup a Code

```php
$code = $registry->findCode('NAICS', '2022', '541511');

if ($code) {
    echo $code->name; // Custom Computer Programming Services
}
```

### Validate a Code

```php
$isValid = $registry->validateCode('UK_SIC', '2026', '01.11');
```

### Hierarchy Browsing

```php
$topLevel = $registry->getChildren('ISIC', '5', null);
$children = $registry->getChildren('ISIC', '5', 'A');
```

### Search / Autocomplete

```php
$results = $registry->search('NACE', '2.1', 'Agriculture', limit: 5);

foreach ($results as $result) {
    echo $result->code->code . ': ' . $result->code->name . ' (Score: ' . $result->score . ")\n";
}
```

### Translations

```php
$translations = $registry->getTranslations('NACE', '2.1', 'A');
// Returns array of ClassificationIndustryTranslation objects
```

## License

MIT License.
