<?php

namespace App\Support\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Pembuat template Excel resmi "Import Data Remaja Binaan".
 *
 * Struktur sheet (urutan = urutan yang dilihat operator):
 * 1. PETUNJUK — dibuka pertama, terkunci, berisi cara pengisian, aturan umum,
 *    tabel keterangan kolom, dan catatan privasi.
 * 2. DATA — header baris 3 (navy, kolom wajib beda warna), baris CONTOH di
 *    baris 4, lalu 200 baris siap isi lengkap dengan gaya, format sel, dan
 *    validasi data (dropdown dari sheet DAFTAR).
 * 3. DAFTAR — sumber dropdown (agama, jenis kelamin, pendidikan, pelatihan,
 *    wilayah) dari database saat template dibuat; disembunyikan + dikunci.
 *
 * Kompatibilitas parser: header DATA memakai
 * {@see SpesifikasiKolomImport::header()} sehingga `ImportService` mengenali
 * kolomnya. Kolom "No" berisi rumus dan diabaikan parser.
 */
final class TemplateExcel
{
    /** Baris header tabel DATA (1 = judul, 2 = legenda). */
    public const BARIS_HEADER = 3;

    /** Baris CONTOH (wajib dihapus operator). */
    public const BARIS_CONTOH = 4;

    /** Baris pertama data. */
    public const BARIS_AWAL_DATA = 5;

    /** Jumlah baris kosong yang dipra-format (gaya + validasi). */
    public const JUMLAH_BARIS_KOSONG = 200;

    private const WARNA_NAVY = 'FF1F3864';

    private const WARNA_OPSIONAL = 'FF44546A';

    private const WARNA_ZEBRA = 'FFF2F5FA';

    private const WARNA_CONTOH = 'FFFFF2CC';

    private const WARNA_BORDER = 'FFB4C6E7';

    private const FONT = 'Calibri';

    private Spreadsheet $spreadsheet;

    private Worksheet $petunjuk;

    private Worksheet $data;

    private Worksheet $daftar;

    /**
     * @param  array<string, array<int, string>>  $referensi  Daftar pilihan dari database.
     */
    public function __construct(private readonly array $referensi)
    {
        $this->spreadsheet = new Spreadsheet;
        $this->spreadsheet->getProperties()
            ->setCreator('Pendekar 1721')
            ->setTitle(SpesifikasiKolomImport::judulTemplate())
            ->setSubject(SpesifikasiKolomImport::subjudulTemplate())
            ->setDescription('Template resmi import data remaja binaan. Tanpa data pribadi.')
            ->setKeywords('template, import, remaja binaan, pendekar 1721');

        $this->petunjuk = $this->spreadsheet->getActiveSheet();
        $this->petunjuk->setTitle('PETUNJUK');

        $this->data = $this->spreadsheet->createSheet();
        $this->data->setTitle('DATA');

        $this->daftar = $this->spreadsheet->createSheet();
        $this->daftar->setTitle('DAFTAR');
    }

    /**
     * Bangun seluruh isi workbook.
     */
    public function bangun(): Spreadsheet
    {
        $this->tulisDaftar();
        $this->tulisPetunjuk();
        $this->tulisData();

        // PETUNJUK tampil pertama saat file dibuka.
        $this->spreadsheet->setActiveSheetIndex(0);

        return $this->spreadsheet;
    }

    /**
     * Simpan workbook ke path tertentu (.xlsx).
     */
    public function simpan(string $path): void
    {
        $this->bangun();

        (new Xlsx($this->spreadsheet))->save($path);
    }

    /**
     * Sheet DAFTAR: sumber dropdown, disembunyikan dan dikunci.
     */
    private function tulisDaftar(): void
    {
        $sheet = $this->daftar;
        $urutan = ['agama', 'jenis_kelamin', 'pendidikan', 'pelatihan', 'wilayah'];

        foreach ($urutan as $index => $kunci) {
            $huruf = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($huruf.'1', strtoupper(str_replace('_', ' ', $kunci)));

            $nilai = array_values(array_filter(
                $this->referensi[$kunci] ?? [],
                fn ($item): bool => trim((string) $item) !== ''
            ));

            if ($nilai === []) {
                $nilai = ['-'];
            }

            foreach ($nilai as $baris => $teks) {
                $sheet->setCellValueExplicit($huruf.($baris + 2), (string) $teks, DataType::TYPE_STRING);
            }

            $panjang = max(array_map(fn ($teks): int => mb_strlen((string) $teks), $nilai));
            $sheet->getColumnDimension($huruf)->setWidth(min(45, max(14, $panjang + 4)));
        }

        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'name' => self::FONT, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::WARNA_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->freezePane('A2');
        $sheet->getProtection()->setSheet(true);
        $sheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    }

    /**
     * Rentang absolut satu daftar pada sheet DAFTAR (untuk validasi data).
     */
    private function rentangDaftar(string $kunci): string
    {
        $urutan = ['agama', 'jenis_kelamin', 'pendidikan', 'pelatihan', 'wilayah'];
        $index = array_search($kunci, $urutan, true);
        $huruf = Coordinate::stringFromColumnIndex(($index === false ? 0 : $index) + 1);

        $jumlah = count(array_filter(
            $this->referensi[$kunci] ?? [],
            fn ($item): bool => trim((string) $item) !== ''
        ));

        $barisAkhir = max(2, $jumlah + 1);

        return "DAFTAR!\${$huruf}\$2:\${$huruf}\${$barisAkhir}";
    }

    /**
     * Sheet PETUNJUK: judul resmi, cara pengisian, aturan umum, tabel
     * keterangan kolom, dan catatan privasi. Dikunci tanpa password.
     */
    private function tulisPetunjuk(): void
    {
        $sheet = $this->petunjuk;
        $sheet->setShowGridlines(false);

        foreach (['A' => 30, 'B' => 16, 'C' => 26, 'D' => 26, 'E' => 50, 'F' => 4] as $kolom => $lebar) {
            $sheet->getColumnDimension($kolom)->setWidth($lebar);
        }

        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', SpesifikasiKolomImport::judulTemplate());
        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A2', SpesifikasiKolomImport::subjudulTemplate());
        $sheet->mergeCells('A3:F3');
        $sheet->setCellValue('A3', SpesifikasiKolomImport::namaInstansi());

        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'name' => self::FONT, 'color' => ['argb' => self::WARNA_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A2:F2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'name' => self::FONT],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A3:F3')->applyFromArray([
            'font' => ['size' => 11, 'name' => self::FONT, 'italic' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $baris = 5;
        $this->judulBagian($sheet, $baris++, 'PETUNJUK PENGISIAN');

        foreach (SpesifikasiKolomImport::langkahPengisian() as $nomor => $langkah) {
            $sheet->mergeCells("A{$baris}:F{$baris}");
            $sheet->setCellValue("A{$baris}", ($nomor + 1).'. '.$langkah);
            $sheet->getStyle("A{$baris}:F{$baris}")->applyFromArray([
                'font' => ['size' => 10, 'name' => self::FONT],
                'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_TOP],
            ]);
            $sheet->getRowDimension($baris)->setRowHeight(30);
            $baris++;
        }

        $baris++;
        $this->judulBagian($sheet, $baris++, 'ATURAN UMUM');

        foreach (SpesifikasiKolomImport::aturanUmum() as $aturan) {
            $sheet->mergeCells("A{$baris}:F{$baris}");
            $sheet->setCellValue("A{$baris}", '• '.$aturan);
            $sheet->getStyle("A{$baris}:F{$baris}")->applyFromArray([
                'font' => ['size' => 10, 'name' => self::FONT],
                'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_TOP],
            ]);
            $sheet->getRowDimension($baris)->setRowHeight(26);
            $baris++;
        }

        $baris++;
        $this->judulBagian($sheet, $baris++, 'KETERANGAN KOLOM');

        $headerKeterangan = ['Nama Kolom', 'Wajib/Opsional', 'Format', 'Contoh', 'Keterangan'];
        foreach ($headerKeterangan as $index => $teks) {
            $huruf = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($huruf.$baris, $teks);
        }
        $sheet->getStyle("A{$baris}:E{$baris}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => self::FONT, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::WARNA_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => $this->gayaBorder(),
        ]);
        $baris++;

        foreach (SpesifikasiKolomImport::kolom() as $kolom) {
            $wajib = $kolom['wajib'] === []
                ? 'Opsional'
                : 'Wajib ('.implode(' & ', array_map(
                    fn (string $mode): string => $mode === 'binaan' ? 'Remaja Binaan' : 'Klien Umum',
                    $kolom['wajib']
                )).')';

            $sheet->setCellValue('A'.$baris, $kolom['header'].(in_array('binaan', $kolom['wajib'], true) ? ' (*)' : ''));
            $sheet->setCellValue('B'.$baris, $wajib);
            $sheet->setCellValue('C'.$baris, $kolom['format']);
            $sheet->setCellValue('D'.$baris, $kolom['contoh']);
            $sheet->setCellValue('E'.$baris, $kolom['keterangan']);

            $sheet->getStyle("A{$baris}:E{$baris}")->applyFromArray([
                'font' => ['size' => 10, 'name' => self::FONT],
                'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_TOP],
                'borders' => $this->gayaBorder(),
            ]);
            $sheet->getRowDimension($baris)->setRowHeight(30);
            $baris++;
        }

        $baris++;
        $this->judulBagian($sheet, $baris++, 'CATATAN PRIVASI');
        $sheet->mergeCells("A{$baris}:F{$baris}");
        $sheet->setCellValue("A{$baris}", SpesifikasiKolomImport::catatanPrivasi());
        $sheet->getStyle("A{$baris}:F{$baris}")->applyFromArray([
            'font' => ['size' => 10, 'name' => self::FONT, 'italic' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::WARNA_CONTOH]],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => $this->gayaBorder(),
        ]);
        $sheet->getRowDimension($baris)->setRowHeight(34);

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.5)->setRight(0.4)->setBottom(0.5)->setLeft(0.4);
        $sheet->getHeaderFooter()->setOddFooter('&C Halaman &P dari &N');
        $sheet->getProtection()->setSheet(true);
    }

    /**
     * Sheet DATA: judul, legenda, header, baris CONTOH, lalu 200 baris siap isi.
     */
    private function tulisData(): void
    {
        $sheet = $this->data;
        $sheet->setShowGridlines(false);

        $headers = SpesifikasiKolomImport::header();
        $definisi = SpesifikasiKolomImport::kolom();
        $hurufAkhir = Coordinate::stringFromColumnIndex(count($headers));
        $barisAkhir = self::BARIS_AWAL_DATA + self::JUMLAH_BARIS_KOSONG - 1;

        foreach ($definisi as $index => $kolom) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($kolom['lebar']);
        }

        // Baris 1: judul tabel.
        $sheet->mergeCells("A1:{$hurufAkhir}1");
        $sheet->setCellValue('A1', 'DATA REMAJA BINAAN — '.SpesifikasiKolomImport::subjudulTemplate());
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getStyle("A1:{$hurufAkhir}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'name' => self::FONT, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::WARNA_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Baris 2: nama instansi + legenda warna.
        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', SpesifikasiKolomImport::namaInstansi());
        $sheet->mergeCells('I2:K2');
        $sheet->setCellValue('I2', 'Wajib diisi (*)');
        $sheet->mergeCells('L2:N2');
        $sheet->setCellValue('L2', 'Kolom opsional');
        $sheet->mergeCells("O2:{$hurufAkhir}2");
        $sheet->setCellValue('O2', 'Tanggal: DD/MM/YYYY  ·  Jenis kelamin: L/P  ·  Hapus baris CONTOH sebelum import');

        $sheet->getStyle('A2:H2')->applyFromArray([
            'font' => ['size' => 10, 'name' => self::FONT, 'italic' => true],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('I2:K2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => self::FONT, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::WARNA_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('L2:N2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => self::FONT, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::WARNA_OPSIONAL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("O2:{$hurufAkhir}2")->applyFromArray([
            'font' => ['size' => 9, 'name' => self::FONT, 'italic' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Baris 3: header kolom (wajib = navy, opsional = abu-biru).
        foreach ($headers as $index => $teks) {
            $huruf = Coordinate::stringFromColumnIndex($index + 1);
            $wajib = in_array('binaan', $definisi[$index]['wajib'], true);

            $sheet->setCellValue($huruf.self::BARIS_HEADER, $teks);
            $sheet->getStyle($huruf.self::BARIS_HEADER)->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'name' => self::FONT, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => $wajib ? self::WARNA_NAVY : self::WARNA_OPSIONAL],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => $this->gayaBorder(),
            ]);
        }
        $sheet->getRowDimension(self::BARIS_HEADER)->setRowHeight(38);

        // Header eksplisit dikunci (bukan hanya mewarisi default gaya sel)
        // supaya nama/urutan kolom yang dibaca parser tidak bisa tertimpa.
        $sheet->getStyle('A'.self::BARIS_HEADER.":{$hurufAkhir}".self::BARIS_HEADER)
            ->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);

        // Baris 4: baris CONTOH (data fiktif, wajib dihapus operator).
        $contoh = SpesifikasiKolomImport::barisContoh([
            'wilayah' => $this->referensi['wilayah'][0] ?? '',
            'pendidikan' => $this->referensi['pendidikan'][0] ?? '',
            'pelatihan' => $this->referensi['pelatihan'][0] ?? '',
            'agama' => $this->referensi['agama'][0] ?? '',
        ]);

        foreach ($contoh as $index => $nilai) {
            $sheet->setCellValueExplicit(
                Coordinate::stringFromColumnIndex($index + 1).self::BARIS_CONTOH,
                (string) $nilai,
                DataType::TYPE_STRING
            );
        }

        $sheet->getStyle('A'.self::BARIS_CONTOH.":{$hurufAkhir}".self::BARIS_CONTOH)->applyFromArray([
            'font' => ['size' => 10, 'name' => self::FONT, 'italic' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::WARNA_CONTOH]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => $this->gayaBorder(),
        ]);
        $sheet->getStyle('B'.self::BARIS_CONTOH)->getFont()->setBold(true);
        $sheet->getRowDimension(self::BARIS_CONTOH)->setRowHeight(30);

        // 200 baris siap isi: rumus nomor urut, gaya seragam, format sel.
        for ($baris = self::BARIS_AWAL_DATA; $baris <= $barisAkhir; $baris++) {
            $sheet->setCellValue('A'.$baris, '=ROW()-'.(self::BARIS_AWAL_DATA - 1));
            $sheet->getStyle("A{$baris}:{$hurufAkhir}{$baris}")->applyFromArray([
                'font' => ['size' => 10, 'name' => self::FONT],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => $this->gayaBorder(),
            ]);
            $sheet->getStyle('A'.$baris)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach ($definisi as $index => $kolom) {
            $huruf = Coordinate::stringFromColumnIndex($index + 1);
            $rentang = $huruf.self::BARIS_AWAL_DATA.':'.$huruf.$barisAkhir;

            $format = match ($kolom['kunci']) {
                'no' => '0',
                'umur' => '0',
                'tanggal_lahir' => 'DD/MM/YYYY',
                default => NumberFormat::FORMAT_TEXT,
            };

            $sheet->getStyle($rentang)->getNumberFormat()->setFormatCode($format);

            if ($kolom['kunci'] === SpesifikasiKolomImport::KUNCI_NOMOR) {
                // Kolom No berisi rumus; sengaja tetap terkunci supaya nomor
                // urut tidak tertimpa saat operator mengisi data.
                $sheet->getStyle($huruf.self::BARIS_CONTOH.':'.$huruf.$barisAkhir)
                    ->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);

                continue;
            }

            // PENTING: baris CONTOH ikut di-unlock. Tanpa ini operator tidak
            // bisa menghapus/mengubah baris CONTOH pada sheet yang diproteksi
            // (Excel menampilkan "The cell or chart you're trying to change is
            // on a protected sheet") — dan file tidak akan pernah bisa diimpor.
            $sheet->getStyle($huruf.self::BARIS_CONTOH.':'.$huruf.$barisAkhir)
                ->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
        }

        // Zebra row halus untuk baris yang sudah berisi data.
        $zebra = new Conditional;
        $zebra->setConditionType(Conditional::CONDITION_EXPRESSION)
            ->setOperatorType(Conditional::OPERATOR_NONE)
            ->addCondition('AND($B'.self::BARIS_AWAL_DATA.'<>"",MOD(ROW(),2)=0)');
        $zebra->getStyle()->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::WARNA_ZEBRA);
        $sheet->setConditionalStyles('A'.self::BARIS_AWAL_DATA.":{$hurufAkhir}{$barisAkhir}", [$zebra]);

        $this->pasangValidasi($barisAkhir);
        $this->pasangCetakDanProteksi($hurufAkhir, $barisAkhir);
    }

    /**
     * Pemasangan validasi data (dropdown, angka, tanggal, panjang teks) untuk
     * seluruh baris siap isi. Pesan & input prompt berbahasa Indonesia.
     */
    private function pasangValidasi(int $barisAkhir): void
    {
        $awal = self::BARIS_AWAL_DATA;

        $rentang = fn (string $kunci): string => $this->hurufKolom($kunci).$awal.':'.$this->hurufKolom($kunci).$barisAkhir;

        $this->validasiDaftar(
            $rentang('jenis_kelamin'),
            'jenis_kelamin',
            'Jenis kelamin tidak valid',
            'Pilih L (laki-laki) atau P (perempuan) dari daftar.',
        );

        $this->validasiDaftar(
            $rentang('agama'),
            'agama',
            'Agama tidak valid',
            'Pilih agama dari daftar pada sheet DAFTAR.',
        );

        $this->validasiDaftar(
            $rentang('pendidikan'),
            'pendidikan',
            'Pendidikan tidak valid',
            'Pilih pendidikan dari daftar pada sheet DAFTAR.',
        );

        $this->validasiDaftar(
            $rentang('pelatihan'),
            'pelatihan',
            'Pelatihan tidak ada di daftar',
            'Pilih dari daftar, atau ketik beberapa pelatihan dipisahkan koma (contoh: Menjahit, Tata Boga).',
            DataValidation::STYLE_WARNING,
        );

        $this->validasiDaftar(
            $rentang('penempatan_wilayah'),
            'wilayah',
            'Wilayah tidak ada di daftar',
            'Pilih dari daftar, atau ketik beberapa wilayah dipisahkan koma.',
            DataValidation::STYLE_WARNING,
        );

        $umur = new DataValidation;
        $umur->setType(DataValidation::TYPE_WHOLE)
            ->setOperator(DataValidation::OPERATOR_BETWEEN)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowErrorMessage(true)
            ->setShowInputMessage(true)
            ->setErrorTitle('Umur tidak valid')
            ->setError('Isi umur dengan bilangan bulat 5 sampai 30 tahun.')
            ->setPromptTitle('Umur')
            ->setPrompt('Bilangan bulat 5-30. Kosongkan bila Tanggal Lahir diisi.')
            ->setFormula1('5')
            ->setFormula2('30');
        $this->data->setDataValidation($rentang('umur'), $umur);

        $tanggal = new DataValidation;
        $tanggal->setType(DataValidation::TYPE_DATE)
            ->setOperator(DataValidation::OPERATOR_BETWEEN)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowErrorMessage(true)
            ->setShowInputMessage(true)
            ->setErrorTitle('Tanggal lahir tidak valid')
            ->setError('Gunakan format DD/MM/YYYY, contoh 17/08/2008.')
            ->setPromptTitle('Tanggal lahir')
            ->setPrompt('Format DD/MM/YYYY (contoh 17/08/2008).')
            ->setFormula1('DATE(1950,1,1)')
            ->setFormula2('DATE('.date('Y').',12,31)');
        $this->data->setDataValidation($rentang('tanggal_lahir'), $tanggal);

        $this->validasiPanjangTeks($rentang('nama'), 2, 150, 'Nama', 'Isi nama lengkap 2-150 karakter.');
        $this->validasiPanjangTeks($rentang('asal_daerah'), 2, 100, 'Asal daerah', 'Isi asal daerah 2-100 karakter.');
        $this->validasiPanjangTeks($rentang('angkatan'), 2, 50, 'Angkatan', 'Isi angkatan 2-50 karakter.');
        $this->validasiPanjangTeks($rentang('email'), 3, 150, 'Email', 'Isi alamat email yang valid atau kosongkan.');
    }

    /**
     * Validasi dropdown dari sheet DAFTAR.
     */
    private function validasiDaftar(string $rentang, string $kunciDaftar, string $judul, string $pesan, string $gaya = DataValidation::STYLE_STOP): void
    {
        $validasi = new DataValidation;
        $validasi->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle($gaya)
            ->setAllowBlank(true)
            ->setShowErrorMessage(true)
            ->setShowInputMessage(true)
            ->setErrorTitle($judul)
            ->setError($pesan)
            ->setPromptTitle($judul)
            ->setPrompt($pesan)
            ->setFormula1($this->rentangDaftar($kunciDaftar));

        $this->data->setDataValidation($rentang, $validasi);
    }

    /**
     * Validasi panjang teks (kolom teks wajib/opsional).
     */
    private function validasiPanjangTeks(string $rentang, int $minimal, int $maksimal, string $judul, string $pesan): void
    {
        $validasi = new DataValidation;
        $validasi->setType(DataValidation::TYPE_TEXTLENGTH)
            ->setOperator(DataValidation::OPERATOR_BETWEEN)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowErrorMessage(true)
            ->setShowInputMessage(true)
            ->setErrorTitle($judul.' tidak valid')
            ->setError($pesan)
            ->setPromptTitle($judul)
            ->setPrompt($pesan)
            ->setFormula1((string) $minimal)
            ->setFormula2((string) $maksimal);

        $this->data->setDataValidation($rentang, $validasi);
    }

    /**
     * Huruf kolom untuk satu kunci kolom.
     */
    private function hurufKolom(string $kunci): string
    {
        $semua = array_column(SpesifikasiKolomImport::kolom(), 'kunci');
        $index = array_search($kunci, $semua, true);

        return Coordinate::stringFromColumnIndex(($index === false ? 0 : $index) + 1);
    }

    /**
     * Pengaturan cetak A4 landscape, header/footer, freeze panes, autofilter,
     * dan proteksi sheet (header terkunci, area data terbuka).
     */
    private function pasangCetakDanProteksi(string $hurufAkhir, int $barisAkhir): void
    {
        $sheet = $this->data;

        $sheet->freezePane('C'.(self::BARIS_HEADER + 1));
        $sheet->setAutoFilter('A'.self::BARIS_HEADER.':'.$hurufAkhir.$barisAkhir);

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(1, self::BARIS_HEADER);

        $sheet->getPageMargins()->setTop(0.4)->setRight(0.3)->setBottom(0.5)->setLeft(0.3);

        $headerFooter = $sheet->getHeaderFooter();
        $headerFooter->setOddHeader(
            '&L'.SpesifikasiKolomImport::namaInstansi().'&R'.SpesifikasiKolomImport::judulTemplate()
        );
        $headerFooter->setOddFooter('&L Template Import Data Remaja Binaan &C Halaman &P dari &N &R Dicetak '.date('d/m/Y'));

        // Sheet DATA tetap diproteksi (header, judul, kolom No) TETAPI semua
        // area isian sudah di-unlock per sel. Izin di bawah ini wajib diberikan
        // supaya operator bisa bekerja normal di sheet yang diproteksi:
        // filter, urutkan, ubah lebar kolom/baris, sisip/hapus baris, dan
        // memilih sel. Tanpa izin ini Excel menolak aksi tersebut dengan pesan
        // "The cell or chart you're trying to change is on a protected sheet".
        $proteksi = $sheet->getProtection();
        $proteksi->setSheet(true);
        $proteksi->setAutoFilter(false);
        $proteksi->setSort(false);
        $proteksi->setFormatColumns(false);
        $proteksi->setFormatRows(false);
        $proteksi->setInsertRows(false);
        $proteksi->setDeleteRows(false);
        $proteksi->setSelectLockedCells(false);
        $proteksi->setSelectUnlockedCells(false);
    }

    /**
     * Judul satu bagian pada sheet PETUNJUK.
     */
    private function judulBagian(Worksheet $sheet, int $baris, string $teks): void
    {
        $sheet->mergeCells("A{$baris}:F{$baris}");
        $sheet->setCellValue("A{$baris}", $teks);
        $sheet->getStyle("A{$baris}:F{$baris}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'name' => self::FONT, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::WARNA_NAVY]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($baris)->setRowHeight(22);
    }

    /**
     * Gaya border tipis untuk tabel PETUNJUK.
     *
     * @return array<string, array<string, mixed>>
     */
    private function gayaBorder(): array
    {
        return [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => self::WARNA_BORDER],
            ],
        ];
    }
}
