<?php

/**
 * Читает CSV-файл. Первая строка используется как имена колонок.
 *
 * @return array<int, array<string, string>>|false
 */
function readCsvAsAssociativeArray(
    string $filename,
    string $delimiter = ',',
    string $enclosure = '"',
    string $escape = ''
) {
    if (!is_file($filename) || !is_readable($filename)) {
        return false;
    }

    $handle = fopen($filename, 'rb');
    if ($handle === false) {
        return false;
    }

    try {
        $headers = fgetcsv($handle, 0, $delimiter, $enclosure, $escape);
        if ($headers === false) {
            return [];
        }

        $headers = array_map(
            static fn($header): string => trim((string)$header),
            $headers
        );

        if (in_array('', $headers, true) || count($headers) !== count(array_unique($headers))) {
            return false;
        }

        $rows = [];

        while (($values = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== false) {
            if ($values === [null] || $values === []) {
                continue;
            }

            if (count($values) !== count($headers)) {
                continue;
            }

            $rows[] = array_combine($headers, array_map(
                static fn($value): string => (string)$value,
                $values
            ));
        }

        return $rows;
    } finally {
        fclose($handle);
    }
}
