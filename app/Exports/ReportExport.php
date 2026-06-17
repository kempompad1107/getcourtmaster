<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Generic, configurable Excel/CSV export for the reports module.
 *
 * Pass a plain array of associative rows; first row's keys become the headers
 * (overridable via $headings). One class so we don't have to spin a new file
 * per report type — the report-specific shaping happens in the controller.
 */
class ReportExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(
        private readonly array $rows,
        private readonly array $headings,
        private readonly string $title = 'Report',
    ) {}

    public function array(): array
    {
        if (empty($this->rows)) {
            return [];
        }
        // Normalise: keep column order aligned with $headings (by key when assoc, else positional).
        $keys = array_keys($this->rows[0]);
        return array_map(fn ($r) => array_map(fn ($k) => $r[$k] ?? null, $keys), $this->rows);
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31); // Excel sheet name limit
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highest = $sheet->getHighestColumn();

                $sheet->getStyle("A1:{$highest}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '198754']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DEE2E6']]],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(22);
                $sheet->freezePane('A2');
            },
        ];
    }
}
