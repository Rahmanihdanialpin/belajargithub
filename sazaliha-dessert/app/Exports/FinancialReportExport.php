<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class FinancialReportExport implements FromView, WithStyles, ShouldAutoSize, WithColumnFormatting
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        // Menggunakan view blade yang berisi template laporan keuangan kamu
        return view('admin.reports.financial-report', $this->data);
    }

    /**
     * Memaksa Excel mengenali kolom numerik agar bisa diformat dengan benar
     */
    public function columnFormats(): array
    {
        return [
            'B' => '#,##0',       // Jumlah Order
            'C' => '"Rp"#,##0',   // Pendapatan
            'D' => '"Rp"#,##0',   // Biaya Modal
            'E' => '"Rp"#,##0',   // Laba
        ];
    }

    /**
     * Tweak tambahan agar layout teks dan angka rapi (tidak menempel ke border)
     */
    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        // Mengatur perataan teks (Kolom A kiri, sisanya kanan untuk angka)
        $sheet->getStyle('A1:A' . $lastRow)->getAlignment()->setHorizontal('left');
        $sheet->getStyle('B5:E' . $lastRow)->getAlignment()->setHorizontal('right');

        // Opsional: Atur font global agar seragam di Excel
        $sheet->getStyle('A1:E' . $lastRow)->getFont()->setName('Arial');
    }
}