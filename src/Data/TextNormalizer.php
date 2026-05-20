<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Data;

final class TextNormalizer
{
    /**
     * Normalizes text according to the package requirements.
     */
    public function normalize(?string $text, bool $isNaicsTitle = false): ?string
    {
        if ($text === null) {
            return null;
        }

        // Normalize Excel-style "_x000D_" artifacts
        $text = str_replace('_x000D_', "\n", $text);

        // Normalize Windows line endings
        $text = str_replace("\r\n", "\n", $text);

        // Normalize repeated whitespace
        $text = (string)preg_replace('/\h+/u', ' ', $text);
        $text = (string)preg_replace('/\n+/u', "\n", $text);

        $text = trim($text);

        // Convert literal "nan" descriptions to null
        if (strtolower($text) === 'nan') {
            return null;
        }

        if ($text === '') {
            return null;
        }

        // Remove known NAICS trailing title artifacts such as a final source-marker "T"
        if ($isNaicsTitle && str_ends_with($text, ' T')) {
            $text = substr($text, 0, -2);
        }

        return $text;
    }
}
