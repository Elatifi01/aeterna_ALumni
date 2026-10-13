<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The spreadsheet an organization downloads and fills in (docs/project.md F12).
 */
class AlumniImportTemplate
{
    /** @var array<string, string> */
    public const COLUMNS = [
        'Prenom' => 'first_name',
        'Nom' => 'last_name',
        'E-mail' => 'email',
        'Telephone' => 'phone',
        'Diplome' => 'degree',
        'Promotion' => 'graduation_year',
        'Entreprise' => 'company',
        'Poste' => 'job_title',
        'Ville' => 'city',
    ];

    public const REQUIRED = ['first_name', 'last_name', 'email'];

    private const EXAMPLE = [
        'Amina', 'Bennani', 'amina.bennani@exemple.com', '0612345678',
        'Master Informatique', '2021', 'Atlas Digital', 'Developpeuse', 'Casablanca',
    ];

    /**
     * The column headings of the template.
     *
     * @return array<int, string>
     */
    public static function headings(): array
    {
        return array_keys(self::COLUMNS);
    }

    /**
     * The name of the downloaded template.
     */
    public function filename(): string
    {
        return 'modele-import-alumni.xlsx';
    }

    /**
     * Write the model file and return its path on disk.
     */
    public function write(string $path): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Alumni');

        $headings = self::headings();

        foreach ($headings as $index => $heading) {
            $column = $index + 1;

            $sheet->setCellValue([$column, 1], $heading);
            $sheet->setCellValue([$column, 2], self::EXAMPLE[$index] ?? '');

            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        $lastColumn = count($headings);

        $header = $sheet->getStyle([1, 1, $lastColumn, 1]);
        $header->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF16375A');
        $header->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $sheet->getStyle([1, 2, $lastColumn, 2])->getFont()->getColor()->setARGB('FF8B909B');

        $sheet->freezePane('A2');

        (new Xlsx($spreadsheet))->save($path);

        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
