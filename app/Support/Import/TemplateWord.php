<?php

namespace App\Support\Import;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Style\ListItem;

/**
 * Pembuat template Word (.docx) resmi "Import Data Remaja Binaan".
 *
 * Aturan penting agar hasilnya tetap terbaca `ImportService::parseWord()`:
 * - tabel DATA adalah satu tabel tanpa sel gabungan (no merge),
 * - tidak ada teks lain di dalam tabel DATA selain header + baris data,
 * - header kolom memakai {@see SpesifikasiKolomImport::header()} (persis sama
 *   dengan template Excel),
 * - baris CONTOH memuat penanda {@see SpesifikasiKolomImport::PENANDA_CONTOH}
 *   sehingga parser menolak file yang lupa menghapusnya.
 */
final class TemplateWord
{
    /** Jumlah baris kosong siap isi di tabel DATA. */
    public const JUMLAH_BARIS_KOSONG = 15;

    private const FONT = 'Arial';

    private const WARNA_NAVY = '1F3864';

    private const WARNA_OPSIONAL = '44546A';

    private const WARNA_ZEBRA = 'F2F5FA';

    private const WARNA_CONTOH = 'FFF2CC';

    private const WARNA_BORDER = 'B4C6E7';

    private const LEBAR_TABEL = 15000;

    private PhpWord $phpWord;

    /**
     * @param  array<string, array<int, string>>  $referensi  Daftar pilihan dari database.
     */
    public function __construct(private readonly array $referensi) {}

    /**
     * Bangun dokumen dan simpan ke path tertentu (.docx).
     */
    public function simpan(string $path): void
    {
        // PhpWord menulis teks apa adanya (output escaping mati secara default),
        // sehingga karakter seperti "&" pada nama instansi membuat .docx tidak
        // valid. Nyalakan escaping selama pembuatan dokumen, lalu pulihkan.
        $escapingSebelumnya = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);

        try {
            $this->bangun($path);
        } finally {
            Settings::setOutputEscapingEnabled($escapingSebelumnya);
        }
    }

    /**
     * Susun seluruh isi dokumen.
     */
    private function bangun(string $path): void
    {
        $this->phpWord = new PhpWord;
        $this->phpWord->setDefaultFontName(self::FONT);
        $this->phpWord->setDefaultFontSize(10);

        $properti = $this->phpWord->getDocInfo();
        $properti->setTitle(SpesifikasiKolomImport::judulTemplate());
        $properti->setSubject(SpesifikasiKolomImport::subjudulTemplate());
        $properti->setCreator('Pendekar 1721');
        $properti->setDescription('Template resmi import data remaja binaan. Tanpa data pribadi.');

        $section = $this->phpWord->addSection([
            'orientation' => 'landscape',
            'paperSize' => 'A4',
            'marginTop' => 900,
            'marginBottom' => 900,
            'marginLeft' => 700,
            'marginRight' => 700,
            'headerHeight' => 450,
            'footerHeight' => 400,
        ]);

        $this->tulisHeader($section);
        $this->tulisFooter($section);
        $this->tulisPetunjuk($section);

        $section->addPageBreak();
        $this->tulisTabelData($section);

        IOFactory::createWriter($this->phpWord, 'Word2007')->save($path);
    }

    /**
     * Header dokumen: nama instansi + judul template.
     */
    private function tulisHeader(Section $section): void
    {
        $header = $section->addHeader();
        $header->addText(SpesifikasiKolomImport::namaInstansi(), ['size' => 9, 'bold' => true], ['alignment' => 'right']);
        $header->addText(SpesifikasiKolomImport::judulTemplate(), ['size' => 9], ['alignment' => 'right']);
    }

    /**
     * Footer dokumen: nomor halaman "Halaman X dari Y".
     */
    private function tulisFooter(Section $section): void
    {
        $footer = $section->addFooter();
        $footer->addPreserveText('Halaman {PAGE} dari {NUMPAGES}', ['size' => 8], ['alignment' => 'center']);
    }

    /**
     * Halaman 1: petunjuk pengisian, aturan umum, keterangan kolom, privasi.
     */
    private function tulisPetunjuk(Section $section): void
    {
        $section->addText(
            SpesifikasiKolomImport::judulTemplate(),
            ['size' => 16, 'bold' => true, 'color' => self::WARNA_NAVY],
            ['alignment' => 'center', 'spaceAfter' => 60]
        );
        $section->addText(
            SpesifikasiKolomImport::subjudulTemplate(),
            ['size' => 11, 'bold' => true],
            ['alignment' => 'center', 'spaceAfter' => 0]
        );
        $section->addText(
            SpesifikasiKolomImport::namaInstansi(),
            ['size' => 10, 'italic' => true],
            ['alignment' => 'center', 'spaceAfter' => 200]
        );

        $this->judulBagian($section, 'PETUNJUK PENGISIAN');

        foreach (SpesifikasiKolomImport::langkahPengisian() as $nomor => $langkah) {
            $section->addText(
                ($nomor + 1).'. '.$langkah,
                ['size' => 10],
                ['indentation' => ['left' => 320, 'hanging' => 320], 'spaceAfter' => 40]
            );
        }

        $section->addTextBreak(1);
        $this->judulBagian($section, 'ATURAN UMUM');

        foreach (SpesifikasiKolomImport::aturanUmum() as $aturan) {
            $section->addListItem($aturan, 0, ['size' => 10], ['listType' => ListItem::TYPE_BULLET_FILLED]);
        }

        $section->addTextBreak(1);
        $this->judulBagian($section, 'KETERANGAN KOLOM');
        $this->tulisTabelKeterangan($section);

        $section->addTextBreak(1);
        $this->judulBagian($section, 'CATATAN PRIVASI');
        $section->addText(
            SpesifikasiKolomImport::catatanPrivasi(),
            ['size' => 10, 'italic' => true],
            ['shading' => ['fill' => self::WARNA_CONTOH], 'spaceBefore' => 60, 'spaceAfter' => 60]
        );
    }

    /**
     * Judul bagian dengan latar navy dan teks putih.
     */
    private function judulBagian(Section $section, string $teks): void
    {
        $section->addText(
            $teks,
            ['size' => 11, 'bold' => true, 'color' => 'FFFFFF'],
            ['shading' => ['fill' => self::WARNA_NAVY], 'spaceBefore' => 120, 'spaceAfter' => 80]
        );
    }

    /**
     * Tabel keterangan kolom (5 kolom, tanpa sel gabungan).
     */
    private function tulisTabelKeterangan(Section $section): void
    {
        $lebar = [2600, 1700, 2600, 2600, 5300];

        $table = $section->addTable([
            'borderSize' => 4,
            'borderColor' => self::WARNA_BORDER,
            'cellMargin' => 60,
            'layout' => 'fixed',
        ]);

        $table->addRow(340, ['tblHeader' => true]);
        foreach (['Nama Kolom', 'Wajib/Opsional', 'Format', 'Contoh', 'Keterangan'] as $index => $teks) {
            $table->addCell($lebar[$index], ['bgColor' => self::WARNA_NAVY, 'valign' => 'center'])
                ->addText($teks, ['size' => 9, 'bold' => true, 'color' => 'FFFFFF'], ['alignment' => 'center']);
        }

        foreach (SpesifikasiKolomImport::kolom() as $kolom) {
            $wajib = $kolom['wajib'] === []
                ? 'Opsional'
                : 'Wajib ('.implode(' & ', array_map(
                    fn (string $mode): string => $mode === 'binaan' ? 'Remaja Binaan' : 'Klien Umum',
                    $kolom['wajib']
                )).')';

            $table->addRow();
            $table->addCell($lebar[0], ['valign' => 'center'])->addText(
                $kolom['header'].(in_array('binaan', $kolom['wajib'], true) ? ' (*)' : ''),
                ['size' => 9]
            );
            $table->addCell($lebar[1], ['valign' => 'center'])->addText($wajib, ['size' => 9]);
            $table->addCell($lebar[2], ['valign' => 'center'])->addText($kolom['format'], ['size' => 9]);
            $table->addCell($lebar[3], ['valign' => 'center'])->addText($kolom['contoh'], ['size' => 9]);
            $table->addCell($lebar[4], ['valign' => 'center'])->addText($kolom['keterangan'], ['size' => 9]);
        }
    }

    /**
     * Tabel DATA: header (diulang tiap halaman), baris CONTOH, 15 baris kosong.
     */
    private function tulisTabelData(Section $section): void
    {
        $section->addText(
            'DATA REMAJA BINAAN',
            ['size' => 14, 'bold' => true, 'color' => self::WARNA_NAVY],
            ['spaceAfter' => 40]
        );
        $section->addText(
            'Hapus baris CONTOH berwarna kuning, lalu isi mulai baris kosong di bawahnya. '
            .'Jangan menggabungkan sel dan jangan mengubah header.',
            ['size' => 9, 'italic' => true],
            ['spaceAfter' => 120]
        );

        $kolom = SpesifikasiKolomImport::kolom();
        $headers = SpesifikasiKolomImport::header();
        $lebar = $this->lebarKolom($kolom);

        $table = $section->addTable([
            'borderSize' => 4,
            'borderColor' => self::WARNA_BORDER,
            'cellMargin' => 40,
            'layout' => 'fixed',
        ]);

        // Header: latar navy, teks putih tebal, diulang di setiap halaman.
        $table->addRow(420, ['tblHeader' => true]);
        foreach ($headers as $index => $teks) {
            $wajib = in_array('binaan', $kolom[$index]['wajib'], true);

            $table->addCell($lebar[$index], [
                'bgColor' => $wajib ? self::WARNA_NAVY : self::WARNA_OPSIONAL,
                'valign' => 'center',
            ])->addText($teks, ['size' => 8, 'bold' => true, 'color' => 'FFFFFF'], ['alignment' => 'center']);
        }

        // Baris CONTOH (fiktif) dengan latar kuning muda.
        $contoh = SpesifikasiKolomImport::barisContoh([
            'wilayah' => $this->referensi['wilayah'][0] ?? '',
            'pendidikan' => $this->referensi['pendidikan'][0] ?? '',
            'pelatihan' => $this->referensi['pelatihan'][0] ?? '',
            'agama' => $this->referensi['agama'][0] ?? '',
        ]);

        $table->addRow(300);
        foreach ($contoh as $index => $nilai) {
            $table->addCell($lebar[$index], ['bgColor' => self::WARNA_CONTOH, 'valign' => 'center'])
                ->addText((string) $nilai, ['size' => 8, 'italic' => true]);
        }

        // Baris kosong siap isi dengan nomor urut dan zebra row halus.
        for ($nomor = 1; $nomor <= self::JUMLAH_BARIS_KOSONG; $nomor++) {
            $warna = $nomor % 2 === 0 ? self::WARNA_ZEBRA : null;

            $table->addRow(300);
            foreach ($headers as $index => $teks) {
                $gaya = ['valign' => 'center'];
                if ($warna !== null) {
                    $gaya['bgColor'] = $warna;
                }

                $isi = $index === 0 ? (string) $nomor : '';
                $table->addCell($lebar[$index], $gaya)->addText($isi, ['size' => 8], ['alignment' => $index === 0 ? 'center' : 'left']);
            }
        }

        $section->addText(
            SpesifikasiKolomImport::catatanPrivasi(),
            ['size' => 8, 'italic' => true],
            ['spaceBefore' => 120, 'shading' => ['fill' => self::WARNA_CONTOH]]
        );
    }

    /**
     * Lebar kolom tabel DATA (twips) agar proporsional pada A4 landscape.
     *
     * @param  array<int, array<string, mixed>>  $kolom
     * @return array<int, int>
     */
    private function lebarKolom(array $kolom): array
    {
        $total = array_sum(array_column($kolom, 'lebar'));
        $faktor = $total > 0 ? self::LEBAR_TABEL / $total : 1;

        return array_map(
            fn (array $definisi): int => max(450, (int) round($definisi['lebar'] * $faktor)),
            $kolom
        );
    }
}
