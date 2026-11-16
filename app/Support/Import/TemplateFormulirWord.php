<?php

namespace App\Support\Import;

use App\Services\ImportService;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Style\ListItem;

/**
 * Pembuat FORMULIR Word (.docx) "satu blok per remaja binaan".
 *
 * Berbeda dari {@see TemplateWord} (tabel horizontal berisi banyak baris pada
 * satu halaman landscape), formulir ini menaruh SATU anak pada SATU halaman
 * A4 portrait berbentuk tabel dua kolom: `Label | Isian`.
 *
 * Aturan yang dijaga agar hasilnya tetap terbaca `ImportService::parseWord()`:
 * - setiap blok adalah tabel TEPAT dua kolom tanpa sel gabungan,
 * - baris pertama tabel adalah header "Label | Isian" (diabaikan parser),
 * - label setiap baris memakai {@see SpesifikasiKolomImport::header()} sehingga
 *   parser mengenali nama kolom yang sama dengan template Excel/Word lama,
 * - judul paragraf "BLOK n" menjadi nomor blok yang dilaporkan Preview,
 * - blok CONTOH memuat penanda {@see SpesifikasiKolomImport::PENANDA_CONTOH}
 *   pada isian Nama, sehingga file yang lupa dikosongkan ditolak parser,
 * - font isian seragam Arial 10 sesuai spesifikasi.
 */
final class TemplateFormulirWord
{
    /** Jumlah blok kosong siap isi (tidak termasuk blok CONTOH). */
    public const JUMLAH_BLOK_KOSONG = 5;

    /** Awalan judul setiap blok; dipakai parser untuk menentukan nomor blok. */
    public const AWALAN_JUDUL_BLOK = 'BLOK';

    /**
     * Kolom yang memakai dropdown CONTENT CONTROL (`w:sdt` + `w:dropDownList`)
     * berisi nilai dari master/enum. Nama kunci sama dengan
     * `SpesifikasiKolomImport::kolom()`.
     */
    public const KOLOM_DROPDOWN = [
        'jenis_kelamin',
        'agama',
        'pendidikan',
        'pelatihan',
        'penempatan_wilayah',
        'kota_ktp',
        'status_pekerjaan',
    ];

    /** Kolom yang memakai pemilih tanggal (`w:sdt` + `w:date`). */
    public const KOLOM_TANGGAL = 'tanggal_lahir';

    /** Teks placeholder pada kontrol yang belum dipilih. */
    public const PLACEHOLDER_MULAI = 'Pilih';

    /** Jumlah nilai yang ditampilkan pada tabel petunjuk sebelum diringkas. */
    private const BATAS_NILAI_PETUNJUK = 10;

    private const FONT = 'Arial';

    private const UKURAN_ISIAN = 10;

    private const WARNA_NAVY = '1F3864';

    private const WARNA_CONTOH = 'FFF2CC';

    private const WARNA_BORDER = 'B4C6E7';

    private const LEBAR_LABEL = 3500;

    private const LEBAR_ISIAN = 6500;

    private PhpWord $phpWord;

    /**
     * @param  array<string, array<int, string>>  $referensi  Daftar pilihan dari database.
     * @param  array<int, string>  $masterKosong  Label kolom yang master datanya belum tersedia.
     */
    public function __construct(
        private readonly array $referensi,
        private readonly array $masterKosong = [],
    ) {}

    /**
     * Bangun dokumen dan simpan ke path tertentu (.docx).
     */
    public function simpan(string $path): void
    {
        // Sama seperti template lain: escaping dinyalakan selama pembuatan
        // dokumen agar karakter seperti "&" pada nama instansi tidak membuat
        // .docx tidak valid.
        $escapingSebelumnya = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);

        try {
            $this->bangun($path);
        } finally {
            Settings::setOutputEscapingEnabled($escapingSebelumnya);
        }
    }

    /**
     * Susun seluruh isi dokumen: petunjuk, blok CONTOH, lalu blok kosong.
     */
    private function bangun(string $path): void
    {
        $this->phpWord = new PhpWord;
        // compatibilityMode 15 (Word 2013+) wajib agar content control
        // (`w:sdt`) tampil sebagai dropdown, bukan kotak teks biasa.
        $this->phpWord->getCompatibility()->setOoxmlVersion(15);
        $this->phpWord->setDefaultFontName(self::FONT);
        $this->phpWord->setDefaultFontSize(self::UKURAN_ISIAN);

        $properti = $this->phpWord->getDocInfo();
        $properti->setTitle(SpesifikasiKolomImport::judulFormulir());
        $properti->setSubject(SpesifikasiKolomImport::subjudulFormulir());
        $properti->setCreator('Pendekar 1721');
        $properti->setDescription('Formulir pendataan remaja binaan per anak. Blok CONTOH berisi data fiktif.');

        $section = $this->phpWord->addSection([
            'orientation' => 'portrait',
            'paperSize' => 'A4',
            'marginTop' => 1000,
            'marginBottom' => 900,
            'marginLeft' => 1000,
            'marginRight' => 900,
            'headerHeight' => 450,
            'footerHeight' => 450,
        ]);

        $this->tulisHeader($section);
        $this->tulisFooter($section);
        $this->tulisPetunjuk($section);

        // Halaman 1 = petunjuk; setiap blok dimulai pada halaman baru.
        $jumlahBlok = self::JUMLAH_BLOK_KOSONG + 1;

        for ($nomor = 1; $nomor <= $jumlahBlok; $nomor++) {
            $section->addPageBreak();
            $this->tulisBlok($section, $nomor, $nomor === 1);
        }

        // Lampiran memuat daftar pilihan LENGKAP (tabel petunjuk hanya
        // meringkas maksimal 10 nilai per kolom).
        $section->addPageBreak();
        $this->tulisLampiranDaftarPilihan($section);

        IOFactory::createWriter($this->phpWord, 'Word2007')->save($path);

        // PhpWord belum mendukung content control: token `[[SDT:blok:kunci]]`
        // yang ditulis pada kolom Isian diganti di sini menjadi `w:sdt` valid.
        $this->pasangKontrolKonten($path);
    }

    /**
     * Header dokumen: nama instansi + judul formulir.
     */
    private function tulisHeader(Section $section): void
    {
        $header = $section->addHeader();
        $header->addText(
            SpesifikasiKolomImport::namaInstansi(),
            ['name' => self::FONT, 'size' => 9, 'bold' => true, 'color' => self::WARNA_NAVY],
            ['alignment' => 'right']
        );
        $header->addText(
            SpesifikasiKolomImport::judulFormulir(),
            ['name' => self::FONT, 'size' => 9],
            ['alignment' => 'right']
        );
    }

    /**
     * Footer dokumen: nomor halaman + catatan privasi.
     */
    private function tulisFooter(Section $section): void
    {
        $footer = $section->addFooter();
        $footer->addPreserveText('Halaman {PAGE} dari {NUMPAGES}', ['name' => self::FONT, 'size' => 8], ['alignment' => 'center']);
        $footer->addText(
            SpesifikasiKolomImport::catatanPrivasi(),
            ['name' => self::FONT, 'size' => 7, 'italic' => true],
            ['alignment' => 'center']
        );
    }

    /**
     * Halaman 1: judul, petunjuk pengisian, aturan umum, label wajib, privasi.
     */
    private function tulisPetunjuk(Section $section): void
    {
        $section->addText(
            SpesifikasiKolomImport::judulFormulir(),
            ['name' => self::FONT, 'size' => 16, 'bold' => true, 'color' => self::WARNA_NAVY],
            ['alignment' => 'center', 'spaceAfter' => 60]
        );
        $section->addText(
            SpesifikasiKolomImport::subjudulFormulir(),
            ['name' => self::FONT, 'size' => 11, 'bold' => true],
            ['alignment' => 'center', 'spaceAfter' => 0]
        );
        $section->addText(
            SpesifikasiKolomImport::namaInstansi(),
            ['name' => self::FONT, 'size' => 10, 'italic' => true],
            ['alignment' => 'center', 'spaceAfter' => 200]
        );

        $this->judulBagian($section, 'PETUNJUK PENGISIAN');

        foreach (SpesifikasiKolomImport::langkahPengisianFormulir() as $nomor => $langkah) {
            $section->addText(
                ($nomor + 1).'. '.$langkah,
                ['name' => self::FONT, 'size' => 10],
                ['indentation' => ['left' => 320, 'hanging' => 320], 'spaceAfter' => 40]
            );
        }

        $section->addTextBreak(1);
        $this->judulBagian($section, 'ATURAN UMUM');

        foreach (SpesifikasiKolomImport::aturanUmumFormulir() as $aturan) {
            $section->addListItem($aturan, 0, ['name' => self::FONT, 'size' => 10], ['listType' => ListItem::TYPE_BULLET_FILLED]);
        }

        $section->addTextBreak(1);
        $this->judulBagian($section, 'LABEL WAJIB (MODE REMAJA BINAAN)');
        $section->addText(
            $this->daftarLabelWajib(),
            ['name' => self::FONT, 'size' => 10],
            ['shading' => ['fill' => self::WARNA_CONTOH], 'spaceBefore' => 60, 'spaceAfter' => 60]
        );

        if ($this->masterKosong !== []) {
            $section->addText(
                'CATATAN: daftar master berikut belum tersedia di server: '.implode(', ', $this->masterKosong)
                .'. Dropdown tetap ada, tetapi hanya menampilkan placeholder "'.self::PLACEHOLDER_MULAI.'..."; '
                .'isi manual atau hubungi admin sebelum formulir dibagikan.',
                ['name' => self::FONT, 'size' => 9, 'italic' => true, 'color' => '9C0006'],
                ['shading' => ['fill' => self::WARNA_CONTOH], 'spaceBefore' => 60, 'spaceAfter' => 120]
            );
        }

        $section->addTextBreak(1);
        $this->tulisTabelNilaiDiterima($section);

        $section->addTextBreak(1);
        $this->judulBagian($section, 'CATATAN PRIVASI');
        $section->addText(
            SpesifikasiKolomImport::catatanPrivasi(),
            ['name' => self::FONT, 'size' => 10, 'italic' => true],
            ['spaceBefore' => 60, 'spaceAfter' => 60]
        );
    }

    /**
     * Satu blok = satu tabel dua kolom `Label | Isian`.
     */
    private function tulisBlok(Section $section, int $nomor, bool $contoh): void
    {
        $judul = self::AWALAN_JUDUL_BLOK.' '.$nomor.($contoh ? ' - CONTOH' : '');
        $this->judulBagian($section, $judul);

        if ($contoh) {
            $section->addText(
                'Blok ini hanya CONTOH. Kosongkan atau hapus seluruh halaman ini sebelum mengunggah formulir.',
                ['name' => self::FONT, 'size' => 9, 'italic' => true, 'color' => '9C0006'],
                ['shading' => ['fill' => self::WARNA_CONTOH], 'spaceAfter' => 120]
            );
        }

        $contohBaris = SpesifikasiKolomImport::barisContoh([
            'wilayah' => $this->referensi['wilayah'][0] ?? '',
            'pendidikan' => $this->referensi['pendidikan'][0] ?? '',
            'pelatihan' => $this->referensi['pelatihan'][0] ?? '',
            'agama' => $this->referensi['agama'][0] ?? '',
        ]);

        $table = $section->addTable([
            'borderSize' => 4,
            'borderColor' => self::WARNA_BORDER,
            'cellMargin' => 60,
            'layout' => 'fixed',
        ]);

        $table->addRow(340, ['tblHeader' => true]);
        $table->addCell(self::LEBAR_LABEL, ['bgColor' => self::WARNA_NAVY, 'valign' => 'center'])
            ->addText('Label', ['name' => self::FONT, 'size' => 9, 'bold' => true, 'color' => 'FFFFFF']);
        $table->addCell(self::LEBAR_ISIAN, ['bgColor' => self::WARNA_NAVY, 'valign' => 'center'])
            ->addText('Isian', ['name' => self::FONT, 'size' => 9, 'bold' => true, 'color' => 'FFFFFF']);

        foreach (SpesifikasiKolomImport::kolom() as $index => $definisi) {
            // Nomor urut hanya relevan pada template tabel; formulir per anak
            // tidak membutuhkannya.
            if ($definisi['kunci'] === SpesifikasiKolomImport::KUNCI_NOMOR) {
                continue;
            }

            $wajib = in_array(ImportService::MODE_BINAAN, $definisi['wajib'], true);
            $label = $definisi['header'].($wajib ? '*' : '');
            $kunci = $definisi['kunci'];
            $memakaiKontrol = in_array($kunci, self::KOLOM_DROPDOWN, true) || $kunci === self::KOLOM_TANGGAL;
            $isi = $memakaiKontrol
                ? KontrolKontenWord::token($nomor, $kunci)
                : ($contoh ? (string) ($contohBaris[$index] ?? '') : '');

            $gayaLabel = ['valign' => 'center'];
            $gayaIsian = ['valign' => 'center'];

            if ($contoh) {
                $gayaLabel['bgColor'] = self::WARNA_CONTOH;
                $gayaIsian['bgColor'] = self::WARNA_CONTOH;
            }

            $table->addRow(420);
            $table->addCell(self::LEBAR_LABEL, $gayaLabel)
                ->addText($label, ['name' => self::FONT, 'size' => 9, 'bold' => $wajib]);
            $table->addCell(self::LEBAR_ISIAN, $gayaIsian)
                ->addText($isi, ['name' => self::FONT, 'size' => self::UKURAN_ISIAN]);
        }
    }

    /**
     * Judul bagian dengan latar navy dan teks putih.
     */
    private function judulBagian(Section $section, string $teks): void
    {
        $section->addText(
            $teks,
            ['name' => self::FONT, 'size' => 11, 'bold' => true, 'color' => 'FFFFFF'],
            ['shading' => ['fill' => self::WARNA_NAVY], 'spaceBefore' => 120, 'spaceAfter' => 80]
        );
    }

    /**
     * Daftar label bertanda `*` pada mode Remaja Binaan.
     */
    private function daftarLabelWajib(): string
    {
        $wajib = [];

        foreach (SpesifikasiKolomImport::kolom() as $definisi) {
            if ($definisi['kunci'] === SpesifikasiKolomImport::KUNCI_NOMOR) {
                continue;
            }

            if (in_array(ImportService::MODE_BINAAN, $definisi['wajib'], true)) {
                $wajib[] = $definisi['header'];
            }
        }

        return 'Wajib diisi: '.implode(', ', $wajib).'. '
            .'Umur boleh dikosongkan bila Tanggal Lahir diisi (umur dihitung otomatis).';
    }

    /**
     * Peta seluruh kontrol (dropdown + tanggal) untuk semua blok.
     *
     * Blok 1 (CONTOH) memakai nilai contoh supaya operator melihat bentuk
     * pilihan yang benar; blok lain dibiarkan placeholder.
     */
    private function pasangKontrolKonten(string $path): void
    {
        $kontrol = [];
        $jumlahBlok = self::JUMLAH_BLOK_KOSONG + 1;

        for ($nomor = 1; $nomor <= $jumlahBlok; $nomor++) {
            foreach (self::KOLOM_DROPDOWN as $kunci) {
                $kontrol[$nomor.':'.$kunci] = [
                    'jenis' => 'dropdown',
                    'alias' => $this->headerKunci($kunci),
                    'tag' => 'pendekar_b'.$nomor.'_'.$kunci,
                    'placeholder' => self::PLACEHOLDER_MULAI.' '.mb_strtolower($this->headerKunci($kunci)).'...',
                    'nilai' => $nomor === 1 ? $this->nilaiContohDropdown($kunci) : null,
                    'items' => $this->itemDropdown($kunci),
                ];
            }

            $kontrol[$nomor.':'.self::KOLOM_TANGGAL] = [
                'jenis' => 'tanggal',
                'alias' => $this->headerKunci(self::KOLOM_TANGGAL),
                'tag' => 'pendekar_b'.$nomor.'_'.self::KOLOM_TANGGAL,
                'placeholder' => self::PLACEHOLDER_MULAI.' tanggal (DD/MM/YYYY)',
                'nilai' => $nomor === 1 ? '17/08/2008' : null,
            ];
        }

        KontrolKontenWord::pasang($path, $kontrol);
    }

    /**
     * Daftar `w:listItem` satu kolom dropdown dari master/enum.
     *
     * @return array<int, array{display: string, value: string}>
     */
    private function itemDropdown(string $kunci): array
    {
        // Referensi Excel memakai kunci `wilayah`; formulir memakai `penempatan_wilayah`.
        $kunciReferensi = $kunci === 'penempatan_wilayah' ? 'wilayah' : $kunci;

        $daftar = $kunci === 'kota_ktp'
            ? ($this->referensi['kota_ktp'] ?? $this->referensi['wilayah'] ?? [])
            : ($this->referensi[$kunciReferensi] ?? []);

        $hasil = [];

        foreach ($daftar as $nilai) {
            $nilai = trim((string) $nilai);

            if ($nilai === '') {
                continue;
            }

            if ($kunci === 'jenis_kelamin') {
                $kode = strtoupper($nilai);
                $hasil[] = [
                    'display' => match ($kode) {
                        'L' => 'L - Laki-laki',
                        'P' => 'P - Perempuan',
                        default => $nilai,
                    },
                    'value' => $kode,
                ];

                continue;
            }

            $hasil[] = ['display' => $nilai, 'value' => $nilai];
        }

        return $hasil;
    }

    /**
     * Nilai contoh untuk dropdown pada blok CONTOH.
     */
    private function nilaiContohDropdown(string $kunci): ?string
    {
        return $this->itemDropdown($kunci)[0]['display'] ?? null;
    }

    /**
     * Header kolom dari definisi kolom (sumber tunggal label).
     */
    private function headerKunci(string $kunci): string
    {
        foreach (SpesifikasiKolomImport::kolom() as $definisi) {
            if ($definisi['kunci'] === $kunci) {
                return $definisi['header'];
            }
        }

        return $kunci;
    }

    /**
     * Tabel ringkas "Nilai yang diterima" pada halaman petunjuk.
     */
    private function tulisTabelNilaiDiterima(Section $section): void
    {
        $this->judulBagian($section, 'NILAI YANG DITERIMA');

        $table = $section->addTable([
            'borderSize' => 4,
            'borderColor' => self::WARNA_BORDER,
            'cellMargin' => 60,
            'layout' => 'fixed',
        ]);

        $table->addRow(340, ['tblHeader' => true]);
        $table->addCell(3200, ['bgColor' => self::WARNA_NAVY, 'valign' => 'center'])
            ->addText('Kolom', ['name' => self::FONT, 'size' => 9, 'bold' => true, 'color' => 'FFFFFF']);
        $table->addCell(6800, ['bgColor' => self::WARNA_NAVY, 'valign' => 'center'])
            ->addText('Nilai yang diterima', ['name' => self::FONT, 'size' => 9, 'bold' => true, 'color' => 'FFFFFF']);

        foreach (self::KOLOM_DROPDOWN as $kunci) {
            $table->addRow(360);
            $table->addCell(3200, ['valign' => 'center'])
                ->addText($this->headerKunci($kunci), ['name' => self::FONT, 'size' => 9, 'bold' => true]);
            $table->addCell(6800, ['valign' => 'center'])
                ->addText($this->ringkasanNilai($kunci), ['name' => self::FONT, 'size' => 9]);
        }

        $table->addRow(360);
        $table->addCell(3200, ['valign' => 'center'])
            ->addText($this->headerKunci(self::KOLOM_TANGGAL), ['name' => self::FONT, 'size' => 9, 'bold' => true]);
        $table->addCell(6800, ['valign' => 'center'])
            ->addText($this->ringkasanNilai(self::KOLOM_TANGGAL), ['name' => self::FONT, 'size' => 9]);
    }

    /**
     * Ringkasan nilai satu kolom: master yang belum tersedia ditulis jelas,
     * daftar panjang diringkas menjadi maksimal 10 nilai.
     */
    private function ringkasanNilai(string $kunci): string
    {
        if ($kunci === self::KOLOM_TANGGAL) {
            return 'Format DD/MM/YYYY (pemilih tanggal, contoh 17/08/2008).';
        }

        $items = $this->itemDropdown($kunci);

        if ($items === []) {
            return 'Belum tersedia (master kosong). Isi manual atau hubungi admin.';
        }

        $total = count($items);
        $tampil = array_slice($items, 0, self::BATAS_NILAI_PETUNJUK);
        $teks = implode(', ', array_map(static fn (array $item): string => $item['display'], $tampil));

        if ($total > self::BATAS_NILAI_PETUNJUK) {
            $teks .= '; dan '.($total - self::BATAS_NILAI_PETUNJUK).' nilai lainnya (lihat lampiran DAFTAR PILIHAN).';
        }

        return $teks;
    }

    /**
     * Lampiran: daftar LENGKAP pilihan dropdown (tanpa pemotongan 10 nilai).
     */
    private function tulisLampiranDaftarPilihan(Section $section): void
    {
        $this->judulBagian($section, 'LAMPIRAN DAFTAR PILIHAN');

        $section->addText(
            'Daftar berikut adalah isi dropdown pada setiap blok formulir. Operator tidak perlu mengetik ulang nilai; '
            .'cukup pilih dari dropdown agar nilainya dikenali saat import.',
            ['name' => self::FONT, 'size' => 9, 'italic' => true],
            ['spaceAfter' => 120]
        );

        $table = $section->addTable([
            'borderSize' => 4,
            'borderColor' => self::WARNA_BORDER,
            'cellMargin' => 60,
            'layout' => 'fixed',
        ]);

        $table->addRow(340, ['tblHeader' => true]);
        $table->addCell(3200, ['bgColor' => self::WARNA_NAVY, 'valign' => 'center'])
            ->addText('Kolom', ['name' => self::FONT, 'size' => 9, 'bold' => true, 'color' => 'FFFFFF']);
        $table->addCell(6800, ['bgColor' => self::WARNA_NAVY, 'valign' => 'center'])
            ->addText('Daftar lengkap', ['name' => self::FONT, 'size' => 9, 'bold' => true, 'color' => 'FFFFFF']);

        foreach (array_merge(self::KOLOM_DROPDOWN, [self::KOLOM_TANGGAL]) as $kunci) {
            $items = $kunci === self::KOLOM_TANGGAL ? [] : $this->itemDropdown($kunci);
            $teks = $kunci === self::KOLOM_TANGGAL
                ? 'Format DD/MM/YYYY (pemilih tanggal).'
                : ($items === []
                    ? 'Belum ada data master.'
                    : implode(', ', array_map(static fn (array $item): string => $item['display'], $items)));

            $table->addRow(360);
            $table->addCell(3200, ['valign' => 'center'])
                ->addText($this->headerKunci($kunci), ['name' => self::FONT, 'size' => 9, 'bold' => true]);
            $table->addCell(6800, ['valign' => 'center'])
                ->addText($teks, ['name' => self::FONT, 'size' => 9]);
        }
    }
}
