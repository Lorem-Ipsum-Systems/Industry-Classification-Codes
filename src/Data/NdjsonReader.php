<?php

declare(strict_types=1);

namespace LoremIpsum\IndustryClassificationCodes\Data;

use RuntimeException;

final class NdjsonReader
{
    /**
     * @return iterable<int, array<string, mixed>>
     */
    public function read(string $filePath): iterable
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException(sprintf('File not found: %s', $filePath));
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Could not open file: %s', $filePath));
        }

        try {
            $lineNumber = 0;
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $trimmed = trim($line);
                if ($trimmed === '') {
                    continue;
                }

                $data = json_decode($trimmed, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException(sprintf(
                        'Invalid JSON in %s on line %d: %s',
                        $filePath,
                        $lineNumber,
                        json_last_error_msg()
                    ));
                }

                yield $lineNumber => $data;
            }
        } finally {
            fclose($handle);
        }
    }
}
