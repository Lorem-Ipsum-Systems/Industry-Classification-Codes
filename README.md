# Industry Classification Codes

A plain PHP package for industry classification reference data, exact code validation, lookup, hierarchy browsing, translations, and simple autocomplete/search.

Supports:
- NAICS 2022
- NACE Rev. 2.1
- UK SIC 2026
- ISIC Rev. 5

## What it does

- Provides industry classification reference data for major international systems.
- Allows exact code validation (canonical codes and supported aliases).
- Supports hierarchy traversal (parents, children, ancestors, descendants).
- Provides translation lookup where available (e.g., NACE).
- Simple case-insensitive autocomplete/search utilities.

## What it does NOT do

- Store company selections (host applications should store `system`, `version`, and `code`).
- Require a database or ORM.
- Require any specific framework.
- Parse XLSX or CSV files at runtime.
- Download datasets or require an internet connection.
- Require an import or normalization step for consumers.
- Provide crosswalks or version mappings in v1.

## Installation

```bash
composer require loremipsum-system/industry-classification-codes
```

## Data Files

The required NDJSON reference data files are shipped directly with the package under the `data/` directory. You do **not** need to add data files manually or run an import command.

The package structure for data is:

```text
data/
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

## Usage

### Initialize the Registry

```php
use LoremIpsum\IndustryClassificationCodes\Registry\ClassificationIndustryRegistry;

// Loads data from the package's default data directory
$registry = ClassificationIndustryRegistry::fromDefaultData();
```

### Lookup and Validation

```php
// Find a code
$code = $registry->findCode(system: 'NAICS', version: '2022', code: '541511');

if ($code) {
    echo $code->title; // Custom Computer Programming Services
}

// Exact validation
$isValid = $registry->isValidCode(system: 'UK_SIC', version: '2026', code: '01.11');

// ISIC Alias lookup support
$isicCode = $registry->findCode(system: 'ISIC', version: '5', code: 'A0111');
// Returns code '0111'
```

### Hierarchy Browsing

```php
// Get direct children
$children = $registry->childrenOf(system: 'NACE', version: '2.1', code: '01');

// Get parent
$parent = $registry->parentOf(system: 'UK_SIC', version: '2026', code: '01.11');

// Get all leaf codes for a system
$leaves = $registry->leafCodes(system: 'ISIC', version: '5');
```

### Search / Autocomplete

```php
$results = $registry->search(system: 'NAICS', version: '2022', query: 'software');

foreach ($results as $result) {
    echo "{$result->code->code}: {$result->code->title} (Score: {$result->score})\n";
}
```

### Translations

```php
$translations = $registry->translationsFor(system: 'NACE', version: '2.1', code: '01.11');
$german = $registry->translationFor(system: 'NACE', version: '2.1', code: '01.11', locale: 'de');
```

## Manual Company Code Validation

Host applications should allow users to select codes and then store them in their own database using three fields: `system`, `version`, and `code`. 

Before saving a selection, the host application should validate the code through this package:

```php
$registry->isValidCode(system: 'NAICS', version: '2022', code: $userInput);
```

## Maintainer Data Updates

To update the reference data:
1. Replace or update the NDJSON files in `data/`.
2. Run tests: `composer test`.
3. Run validation: `composer validate-data`.
4. Commit changes and publish a new release.

Package consumers should never need to perform these steps.

## Roadmap

- Cross-system and version-to-version crosswalks.
- RDF/SKOS exporters.
- Framework-specific adapters (Symfony Bundle, Laravel Service Provider).
- Optional search index integrations (SQLite FTS, Meilisearch).

## License

MIT License.
