<?php

namespace App\Services\Import;

use App\Models\Alumni;
use App\Models\Organization;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exporting the alumni list to a spreadsheet (docs/project.md F13).
 */
class AlumniExporter
{
    private const PORTAL_COLUMNS = ['Compte portail', 'Acces portail', 'Derniere connexion'];

    private const CHUNK = 500;

    /**
     * Write the export and return its path.
     *
     * @param  array<string, mixed>  $filters  the alumni list filters, so the file matches what the screen shows
     */
    public function write(string $path, array $filters = []): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Alumni');

        $headings = [...AlumniImportTemplate::headings(), ...self::PORTAL_COLUMNS];

        foreach ($headings as $index => $heading) {
            $sheet->setCellValue([$index + 1, 1], $heading);
        }

        $row = 2;

        Alumni::query()
            ->filtered($filters)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->chunkById(self::CHUNK, function ($alumni) use ($sheet, &$row): void {
                foreach ($alumni as $one) {
                    foreach (array_values($this->cells($one)) as $index => $value) {
                        $sheet->setCellValueExplicit(
                            [$index + 1, $row],
                            $value,
                            DataType::TYPE_STRING,
                        );
                    }

                    $row++;
                }
            });

        $lastColumn = count($headings);

        $header = $sheet->getStyle([1, 1, $lastColumn, 1]);
        $header->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF16375A');
        $header->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);

        for ($column = 1; $column <= $lastColumn; $column++) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        $sheet->freezePane('A2');

        (new Xlsx($spreadsheet))->save($path);

        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /**
     * How many rows the export will hold, for the screen to say so beforehand.
     *
     * @param  array<string, mixed>  $filters
     */
    public function count(array $filters = []): int
    {
        return Alumni::query()->filtered($filters)->count();
    }

    /**
     * The name of the downloaded file.
     */
    public function filename(Organization $organization): string
    {
        return 'alumni-'.Str::slug($organization->name).'-'.now()->format('Y-m-d').'.xlsx';
    }

    /**
     * One row, in heading order.
     *
     * @return array<int, string>
     */
    private function cells(Alumni $alumni): array
    {
        $values = [];

        foreach (AlumniImportTemplate::COLUMNS as $attribute) {
            $value = $alumni->getAttribute($attribute);

            $values[] = $value === null ? '' : (string) $value;
        }

        $values[] = $alumni->has_account ? 'Oui' : 'Non';
        $values[] = $alumni->has_account ? ($alumni->portal_access ? 'Actif' : 'Suspendu') : '';
        $values[] = $alumni->last_login_at?->format('Y-m-d H:i') ?? '';

        return $values;
    }
}
