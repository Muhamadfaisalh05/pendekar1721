<?php

namespace App\Support\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Laporan hasil import per baris (.xlsx).
 *
 * SENGAJA tanpa email, alamat KTP, atau kolom sensitif lain: laporan ini
 * dipakai untuk pemeriksaan operasional dan bisa diarsipkan/dibagikan lebih
 * luas daripada file mentah. Nama tetap ditampilkan karena operator perlu
 * mencocokkan baris dengan data anak; ID anak dan status akun dipakai sebagai
 * bukti hasil import.
 */
final class LaporanHasilImportExcel
{
    private const WARNA_NAVY = 'FF1F3864';

    private const FONT = 'Calibri';

    /**
     * @param  array{baris: array<int, array<string, mixed>>, ringkasan: array<string, int>}  $laporan
     */
    public function simpan(array $laporan, string $path): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('Pendekar 1721')
            ->setTitle('Laporan Hasil Import Data Remaja Binaan')
            ->setDescription('Laporan operasional tanpa email/alamat.');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('HASIL IMPORT');
        $this->tulis($sheet, $laporan);

        (new Xlsx($spreadsheet))->save($path);
    }

    /**
     * @param  array{baris: array<int, array<string, mixed>>, ringkasan: array<string, int>}  $laporan
     */
    private function tulis(Worksheet $sheet, array $laporan): void
    {
        $sheet->setShowGridlines(false);

        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'LAPORAN HASIL IMPORT DATA REMAJA BINAAN');
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'name' => self::FONT, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::WARNA_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $ringkasan = $laporan['ringkasan'] ?? [];
        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue(
            'A2',
            'Total '.($ringkasan['total'] ?? 0)
            .' baris | Berhasil: '.($ringkasan['berhasil'] ?? 0)
            .' | Dilewati: '.($ringkasan['dilewati'] ?? 0)
            .' | Gagal: '.($ringkasan['gagal'] ?? 0)
            .' | Laporan tanpa email/alamat KTP.'
        );
        $sheet->getStyle('A2:F2')->applyFromArray([
            'font' => ['size' => 10, 'name' => self::FONT, 'italic' => true],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $header = ['No / Blok', 'Nama', 'ID Anak', 'Status Akun', 'Hasil', 'Keterangan'];
        foreach ($header as $index => $teks) {
            $huruf = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($huruf.'4', $teks);
        }

        $sheet->getStyle('A4:F4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => self::FONT, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::WARNA_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(28);

        $baris = 5;

        foreach (($laporan['baris'] ?? []) as $hasil) {
            $nomor = ! empty($hasil['blok']) ? 'Blok '.$hasil['blok'] : (string) ($hasil['row'] ?? '-');

            $nilai = [
                $nomor,
                (string) ($hasil['nama'] ?? ''),
                (string) (($hasil['anak_id'] ?? null) ?: '-'),
                (string) ($hasil['status_akun'] ?? '-'),
                (string) ($hasil['hasil'] ?? '-'),
                (string) ($hasil['keterangan'] ?? ''),
            ];

            foreach ($nilai as $index => $teks) {
                $huruf = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValueExplicit($huruf.$baris, $teks, DataType::TYPE_STRING);
            }

            $sheet->getStyle("A{$baris}:F{$baris}")->applyFromArray([
                'font' => ['size' => 10, 'name' => self::FONT],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFB4C6E7'],
                    ],
                ],
            ]);

            $baris++;
        }

        foreach (['A' => 14, 'B' => 30, 'C' => 14, 'D' => 16, 'E' => 14, 'F' => 55] as $huruf => $lebar) {
            $sheet->getColumnDimension($huruf)->setWidth($lebar);
        }

        $sheet->freezePane('A5');
        $sheet->setAutoFilter('A4:F'.max(4, $baris - 1));
        $sheet->getPageSetup()->setOrientation('landscape')->setPaperSize(9)->setFitToWidth(1);
    }
}
