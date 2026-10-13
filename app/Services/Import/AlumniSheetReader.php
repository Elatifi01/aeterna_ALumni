<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use Throwable;

/**
 * Turns an uploaded spreadsheet into rows (docs/project.md F12).
 */
class AlumniSheetReader
{
    public const MAX_ROWS = 10000;

    /**
     * Read the spreadsheet into rows keyed by the template's headings.
     *
     * @return array{ ok: bool, error: string|null, rows: list<array{line: int, values: array<string, string>}> }
     */
    public function read(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (ReaderException) {
            return $this->failure('Ce fichier n a pas pu etre lu. Utilisez le modele au format Excel ou CSV.');
        } catch (Throwable) {
            return $this->failure('Ce fichier n a pas pu etre lu.');
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        $spreadsheet->disconnectWorksheets();

        if ($rows === []) {
            return $this->failure('Ce fichier est vide.');
        }

        $headings = array_shift($rows);

        $mapping = $this->mapHeadings($headings);

        if ($mapping === null) {
            return $this->failure(
                'Les colonnes ne correspondent pas au modele. Telechargez le modele et conservez la premiere ligne.',
            );
        }

        $parsed = [];

        foreach ($rows as $index => $row) {
            if (count($parsed) >= self::MAX_ROWS) {
                break;
            }

            $values = [];

            foreach ($mapping as $column => $attribute) {
                $values[$attribute] = $this->text($row[$column] ?? null);
            }

            if (implode('', $values) === '') {
                continue;
            }

            $parsed[] = [
                'line' => $index + 2,
                'values' => $values,
            ];
        }

        if ($parsed === []) {
            return $this->failure('Ce fichier ne contient aucune ligne a importer.');
        }

        return ['ok' => true, 'error' => null, 'rows' => $parsed];
    }

    /**
     * Match the heading row against the template.
     *
     * @param  array<int, mixed>  $headings
     * @return array<int, string>|null column index => attribute
     */
    private function mapHeadings(array $headings): ?array
    {
        $expected = [];

        foreach (AlumniImportTemplate::COLUMNS as $heading => $attribute) {
            $expected[$this->fold($heading)] = $attribute;
        }

        $mapping = [];

        foreach ($headings as $index => $heading) {
            $key = $this->fold($this->text($heading));

            if ($key !== '' && isset($expected[$key])) {
                $mapping[$index] = $expected[$key];
            }
        }

        foreach (AlumniImportTemplate::REQUIRED as $attribute) {
            if (! in_array($attribute, $mapping, true)) {
                return null;
            }
        }

        return $mapping;
    }

    /**
     * A trimmed string from a cell value, or null.
     */
    private function text(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }

        return '';
    }

    /**
     * A heading lower-cased and stripped of accents, for matching.
     */
    private function fold(string $value): string
    {
        $value = str($value)->squish()->lower()->ascii()->value();

        return preg_replace('/[^a-z0-9]/', '', $value) ?? '';
    }

    /**
     * The exception raised when the file cannot be read.
     *
     * @return array{ok: bool, error: string, rows: list<array{line: int, values: array<string, string>}>}
     */
    private function failure(string $message): array
    {
        return ['ok' => false, 'error' => $message, 'rows' => []];
    }
}
