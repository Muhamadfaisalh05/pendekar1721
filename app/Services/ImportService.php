<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\MasterCity;
use App\Models\MasterEducationDegree;
use App\Models\MasterTraining;
use App\Models\User;
use App\Modules\Absensi\Models\Anak;
use App\Support\Import\KontrolKontenWord;
use App\Support\Import\SpesifikasiKolomImport;
use App\Support\Import\TemplateFormulirWord;
use App\Support\SchemaGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;

/**
 * Parser, validator, dan penyimpan file import pada menu Import Data.
 *
 * Dua mode (FASE 3):
 * - `MODE_BINAAN` (Remaja Binaan): SETIAP baris membuat data anak
 *   (`semarak.anak`, status Aktif) lalu akun klien `Draft` yang tertaut lewat
 *   `users.anak_id` (email & kata sandi boleh NULL, belum bisa login sampai
 *   proses kelulusan). Opsi operator "Tautkan ke anak yang sudah ada" membuat
 *   baris yang cocok HANYA membuat akun Draft tanpa anak baru.
 * - `MODE_KLIEN` (Klien Umum): perilaku lama — satu baris = satu akun
 *   `Published` yang langsung bisa login, tanpa data anak.
 *
 * Kolom yang dikenali didefinisikan di {@see SpesifikasiKolomImport} supaya
 * template Excel/Word yang diunduh operator selalu cocok dengan parser.
 */
class ImportService
{
    public const MODE_BINAAN = 'binaan';

    public const MODE_KLIEN = 'klien';

    /**
     * Jumlah baris maksimum yang dipindai untuk mencari baris header.
     *
     * Template baru menaruh judul & legenda di atas header (baris 3), template
     * lama menaruh header di baris 1. Pemindaian membuat keduanya terbaca.
     */
    private const BATAS_PINDAI_HEADER = 40;

    /**
     * Pilihan mode untuk form Import Data.
     *
     * @return array<string, string>
     */
    public static function pilihanMode(): array
    {
        return [
            self::MODE_BINAAN => 'Remaja Binaan (anak baru + akun Draft, aktif saat lulus)',
            self::MODE_KLIEN => 'Klien Umum (akun langsung aktif, tanpa data anak)',
        ];
    }

    protected $cities;

    protected $educations;

    protected $trainings;

    public function __construct()
    {
        $this->cities = MasterCity::pluck('id', 'title')->mapWithKeys(function ($item, $key) {
            return [strtolower(trim((string) $key)) => $item];
        })->toArray();

        $this->educations = MasterEducationDegree::pluck('id', 'title')->mapWithKeys(function ($item, $key) {
            return [strtolower(trim((string) $key)) => $item];
        })->toArray();

        $this->trainings = MasterTraining::pluck('id', 'title')->mapWithKeys(function ($item, $key) {
            return [strtolower(trim((string) $key)) => $item];
        })->toArray();
    }

    /**
     * Baca file, validasi isinya, dan susun rencana import (untuk Preview).
     *
     * @param  string  $filePath  Path file yang sudah diunggah.
     * @param  string  $extension  xlsx/xls/csv/docx.
     * @param  string  $mode  `binaan` atau `klien`; menentukan kolom wajib.
     * @param  bool  $tautkanAnak  Keadaan checkbox "Tautkan ke anak yang sudah ada".
     * @param  bool  $imporGanda  Keadaan checkbox "Impor tetap baris ganda".
     * @return array<string, mixed>
     */
    public function parseAndValidate($filePath, $extension, string $mode = self::MODE_KLIEN, bool $tautkanAnak = false, bool $imporGanda = false)
    {
        $rows = [];

        if (in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            $rows = $this->parseExcel($filePath);
        } elseif ($extension === 'docx') {
            $rows = $this->parseWord($filePath);
        } else {
            throw new \Exception("Format file tidak didukung: {$extension}");
        }

        // SECURITY (FASE 3): batasi jumlah baris agar file raksasa tidak
        // menghabiskan memori/CPU server (DoS).
        $maxRows = (int) config('pendekar.import.max_rows', 2000);

        if ($maxRows > 0 && count($rows) > $maxRows) {
            throw new \Exception(
                'File berisi '.count($rows)." baris, melebihi batas {$maxRows} baris. Pecah file menjadi beberapa bagian."
            );
        }

        $validasi = $this->validateRows($rows, $mode);
        $validasi['mode'] = $mode;
        $validasi['tautkan_anak'] = $mode === self::MODE_BINAAN && $tautkanAnak;
        $validasi['impor_ganda'] = $mode === self::MODE_BINAAN && $imporGanda;

        // FASE 3 (revisi review): dua laporan tambahan untuk langkah Preview.
        // Penautan hanya sebagai rencana (checkbox operator). Analisis duplikat
        // pada mode binaan memakai identitas lengkap: nama + alamat KTP +
        // tanggal lahir + asal daerah + angkatan (lihat analisisDuplikatBinaan).
        $validasi['tautan'] = $this->petaTautanAnak($validasi['valid'], $validasi['tautkan_anak']);
        $validasi['duplikat'] = $mode === self::MODE_BINAAN
            ? $this->analisisDuplikatBinaan($validasi['valid'])
            : ['pasti' => [], 'kemungkinan' => [], 'dilewati' => []];

        return $validasi;
    }

    /**
     * Baca sheet DATA (atau sheet pertama yang punya baris header) menjadi
     * daftar baris terpetakan.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseExcel($filePath)
    {
        // setReadDataOnly(true): jangan memuat style, objek, atau makro dari
        // file yang diunggah pengguna; hanya nilai sel yang dibutuhkan.
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($filePath);

        // Template baru: sheet DATA. Template lama: satu sheet dengan header
        // di baris 1. Keduanya dicari lewat baris header, bukan nama urutan.
        $worksheet = $spreadsheet->getSheetByName('DATA');

        if ($worksheet === null) {
            foreach ($spreadsheet->getWorksheetIterator() as $kandidat) {
                if ($this->cariBarisHeader($this->matriksWorksheet($kandidat)) !== null) {
                    $worksheet = $kandidat;

                    break;
                }
            }
        }

        if ($worksheet === null) {
            $worksheet = $spreadsheet->getActiveSheet();
        }

        return $this->petakanBaris($this->matriksWorksheet($worksheet));
    }

    /**
     * Ubah worksheet menjadi matriks nilai (indeks 0 = baris 1).
     *
     * @return array<int, array<int, mixed>>
     */
    protected function matriksWorksheet(Worksheet $worksheet): array
    {
        return array_values($worksheet->toArray(null, true, false, false));
    }

    /**
     * Baca dokumen Word dan kenali SALAH SATU dari dua format yang didukung:
     *
     * 1. Tabel horizontal lama / template {@see SpesifikasiKolomImport} Word:
     *    satu tabel dengan baris header kolom (Nama, Jenis Kelamin, dst.).
     *    Tabel keterangan tidak pernah dianggap tabel data karena header-nya
     *    tidak memuat kolom "Nama" (hanya "Nama Kolom").
     * 2. Formulir per anak ({@see TemplateFormulirWord}):
     *    banyak tabel dua kolom `Label | Isian`, satu tabel per blok/halaman.
     *    Nomor blok diambil dari judul "BLOK n" sebelum tabel (fallback:
     *    urutan blok). Tabel yang seluruh isiannya kosong diabaikan sehingga
     *    blok kosong pada template tidak pernah menjadi baris data.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseWord($filePath)
    {
        // Buka content control (`w:sdt`) pada salinan sementara; reader
        // PhpWord tidak mengenal sdt sehingga nilai dropdown bisa hilang.
        $berkasBaca = KontrolKontenWord::ratakan($filePath);

        try {
            return $this->parseWordDariBerkas($berkasBaca);
        } finally {
            if ($berkasBaca !== $filePath && is_file($berkasBaca)) {
                @unlink($berkasBaca);
            }
        }
    }

    /**
     * Isi `parseWord()` untuk berkas yang sudah siap dibaca PhpWord.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseWordDariBerkas($filePath)
    {
        $phpWord = WordIOFactory::load($filePath);
        $barisFormulir = [];
        $blokKe = 0;

        foreach ($phpWord->getSections() as $section) {
            $judulBlok = null;

            foreach ($section->getElements() as $element) {
                if (! $element instanceof Table) {
                    $teks = $this->teksElemen($element);

                    if ($teks !== '' && preg_match('/BLOK\s+(\d+)/i', $teks, $cocok) === 1) {
                        $judulBlok = (int) $cocok[1];
                    }

                    continue;
                }

                $matriks = $this->matriksTabel($element);

                // Format lama: tabel horizontal dengan baris header kolom.
                if ($this->cariBarisHeader($matriks) !== null) {
                    return $this->petakanBaris($matriks);
                }

                $blok = $this->petakanBlokFormulir($matriks);

                if ($blok === null) {
                    continue;
                }

                $blokKe++;
                $nomorBlok = $judulBlok ?? $blokKe;
                $judulBlok = null;

                // Tabel kosong (blok yang belum diisi) bukan data dan bukan
                // error; abaikan sepenuhnya.
                if (! $this->barisBerisiData($blok)) {
                    continue;
                }

                $blok['_row_number'] = $nomorBlok;
                $blok['_blok'] = $nomorBlok;

                if ($this->mengandungPenandaContoh(array_values($blok))) {
                    $blok['_contoh'] = true;
                }

                $barisFormulir[] = $blok;
            }
        }

        return $barisFormulir;
    }

    /**
     * Petakan tabel dua kolom `Label | Isian` menjadi satu baris data.
     *
     * Mengembalikan null bila tabel bukan blok formulir: setiap baris harus
     * tepat dua kolom, dan minimal dua label dikenali (salah satunya "Nama").
     * Baris yang labelnya tidak dikenal (mis. header "Label"/"Isian") dilewati.
     *
     * @param  array<int, array<int, string>>  $matriks
     * @return array<string, mixed>|null
     */
    protected function petakanBlokFormulir(array $matriks): ?array
    {
        $labelDikenal = $this->labelFormulirDikenal();
        $hasil = [];
        $jumlahLabel = 0;

        foreach ($matriks as $sel) {
            if (count($sel) !== 2) {
                return null;
            }

            $label = strtolower(trim(str_replace('*', '', (string) ($sel[0] ?? ''))));
            $label = (string) preg_replace('/\s+/', ' ', $label);

            if ($label === '' || ! isset($labelDikenal[$label])) {
                continue;
            }

            $jumlahLabel++;
            $hasil[$label] = $this->normalisasiNilaiFormulir($label, (string) ($sel[1] ?? ''));
        }

        if ($jumlahLabel < 2 || ! array_key_exists('nama', $hasil)) {
            return null;
        }

        return $hasil;
    }

    /**
     * Normalkan nilai isian yang datang dari content control Word.
     *
     * - placeholder "Pilih ..." dianggap KOSONG (blok tidak diisi),
     * - display text "L - Laki-laki" / "P - Perempuan" disimpan sebagai L/P,
     * - status pekerjaan dari dropdown disimpan dalam bentuk baku.
     */
    protected function normalisasiNilaiFormulir(string $label, string $nilai): string
    {
        $nilai = trim((string) preg_replace('/\s+/', ' ', trim($nilai)));

        if ($nilai === '') {
            return '';
        }

        if (preg_match('/^pilih(\s|\.\.\.|$)/iu', $nilai) === 1) {
            return '';
        }

        if ($label === 'jenis kelamin') {
            if (preg_match('/^([LP])\s*[-\x{2013}\x{2014}]/iu', $nilai, $cocok) === 1) {
                return strtoupper($cocok[1]);
            }

            if (preg_match('/^(laki-laki|perempuan)$/iu', $nilai, $cocok) === 1) {
                return mb_strtolower($cocok[1]) === 'laki-laki' ? 'L' : 'P';
            }
        }

        if ($label === 'status pekerjaan') {
            if (preg_match('/^(sedang bekerja|bekerja)$/iu', $nilai) === 1) {
                return 'Sedang Bekerja';
            }

            if (preg_match('/^(belum bekerja|tidak bekerja)$/iu', $nilai) === 1) {
                return 'Belum Bekerja';
            }
        }

        return $nilai;
    }

    /**
     * Daftar label kolom (huruf kecil, tanpa `*`) yang dikenali pada formulir.
     *
     * @return array<string, true>
     */
    protected function labelFormulirDikenal(): array
    {
        $label = [];

        foreach (SpesifikasiKolomImport::kolom() as $kolom) {
            if ($kolom['kunci'] === SpesifikasiKolomImport::KUNCI_NOMOR) {
                continue;
            }

            $label[strtolower($kolom['header'])] = true;
        }

        return $label;
    }

    /**
     * Ambil teks dari elemen paragraf Word (Text/TextRun/ListItemRun).
     */
    protected function teksElemen($element): string
    {
        if ($element instanceof Text) {
            return $element->getText();
        }

        if ($element instanceof TextRun || $element instanceof ListItemRun) {
            $teks = '';

            foreach ($element->getElements() as $anak) {
                if ($anak instanceof Text) {
                    $teks .= $anak->getText();
                }
            }

            return $teks;
        }

        return '';
    }

    /**
     * Ubah tabel Word menjadi matriks teks (indeks 0 = baris pertama tabel).
     *
     * @return array<int, array<int, string>>
     */
    protected function matriksTabel(Table $table): array
    {
        $matriks = [];

        foreach ($table->getRows() as $row) {
            $sel = [];

            foreach ($row->getCells() as $cell) {
                $sel[] = trim($this->teksSel($cell));
            }

            $matriks[] = $sel;
        }

        return $matriks;
    }

    /**
     * Ambil seluruh teks dari satu sel Word (TextRun/Text/ListItemRun).
     */
    protected function teksSel($cell): string
    {
        $teks = '';

        foreach ($cell->getElements() as $element) {
            if ($element instanceof Text) {
                $teks .= $element->getText();

                continue;
            }

            if ($element instanceof TextRun || $element instanceof ListItemRun) {
                foreach ($element->getElements() as $anak) {
                    if ($anak instanceof Text) {
                        $teks .= $anak->getText();
                    }
                }
            }
        }

        return (string) preg_replace('/\s+/', ' ', $teks);
    }

    /**
     * Kunci header yang sudah dinormalkan: huruf kecil, tanpa `*`, spasi rapat.
     *
     * @param  array<int, mixed>  $sel
     * @return array<string, true>
     */
    protected function kunciHeader(array $sel): array
    {
        $kunci = [];

        foreach ($sel as $nilai) {
            $nama = strtolower(trim(str_replace('*', '', (string) $nilai)));
            $nama = (string) preg_replace('/\s+/', ' ', $nama);

            if ($nama !== '') {
                $kunci[$nama] = true;
            }
        }

        return $kunci;
    }

    /**
     * Indeks baris header di dalam matriks, atau null bila tidak ditemukan.
     */
    protected function cariBarisHeader(array $matriks): ?int
    {
        $batas = min(count($matriks), self::BATAS_PINDAI_HEADER);

        for ($index = 0; $index < $batas; $index++) {
            $kunci = $this->kunciHeader($matriks[$index] ?? []);

            // "Nama" wajib ada, ditambah satu kolom khas import agar tabel
            // keterangan (header "Nama Kolom") tidak salah dikenali.
            if (isset($kunci['nama']) && (isset($kunci['jenis kelamin']) || isset($kunci['email']) || isset($kunci['asal daerah']))) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Petakan baris data mengikuti header yang ditemukan.
     *
     * @param  array<int, array<int, mixed>>  $matriks
     * @return array<int, array<string, mixed>>
     */
    protected function petakanBaris(array $matriks): array
    {
        $indeksHeader = $this->cariBarisHeader($matriks);

        if ($indeksHeader === null) {
            throw new \Exception(
                'Baris header kolom tidak ditemukan. Pastikan baris header (Nama, Jenis Kelamin, dst.) '
                .'tidak dihapus atau diubah, lalu unggah ulang.'
            );
        }

        $header = [];

        foreach ($matriks[$indeksHeader] as $nilai) {
            $header[] = strtolower(trim(str_replace('*', '', (string) $nilai)));
        }

        $rows = [];
        $jumlahBaris = count($matriks);

        for ($index = $indeksHeader + 1; $index < $jumlahBaris; $index++) {
            $sel = $matriks[$index];
            $mapped = [];

            foreach ($header as $posisi => $namaKolom) {
                if ($namaKolom === '') {
                    continue;
                }

                $mapped[$namaKolom] = $sel[$posisi] ?? null;
            }

            if (! $this->barisBerisiData($mapped)) {
                continue;
            }

            $mapped['_row_number'] = $index + 1;

            if ($this->mengandungPenandaContoh($sel)) {
                $mapped['_contoh'] = true;
            }

            $rows[] = $mapped;
        }

        return $rows;
    }

    /**
     * Baris dianggap berisi data hanya bila salah satu kolom data terisi.
     *
     * Kunci `$mapped` mengikuti HEADER (huruf kecil, tanpa `*`), bukan kunci
     * internal snake_case, jadi pemeriksaan harus memakai header yang sama.
     * Kolom nomor urut ("No") berisi rumus pada 200 baris pra-format sehingga
     * TIDAK boleh dipakai sebagai penanda "baris berisi data".
     *
     * @param  array<string, mixed>  $mapped
     */
    protected function barisBerisiData(array $mapped): bool
    {
        foreach (SpesifikasiKolomImport::kolom() as $kolom) {
            if ($kolom['kunci'] === SpesifikasiKolomImport::KUNCI_NOMOR) {
                continue;
            }

            $nilai = trim((string) ($mapped[strtolower($kolom['header'])] ?? ''));

            if ($nilai !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Baris contoh template masih ada (ditandai "CONTOH - HAPUS SEBELUM IMPORT")?
     *
     * @param  array<int, mixed>  $sel
     */
    protected function mengandungPenandaContoh(array $sel): bool
    {
        foreach ($sel as $nilai) {
            if (str_contains(strtoupper((string) $nilai), 'CONTOH - HAPUS')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validasi per baris. Mode `binaan` mewajibkan kolom data anak; mode
     * `klien` mempertahankan aturan lama (Nama, Email, Jenis Kelamin).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{valid: array<int, array<string, mixed>>, errors: array<int, array<string, mixed>>, duplicates: array<int, array<string, mixed>>, total: int}
     */
    protected function validateRows(array $rows, string $mode = self::MODE_KLIEN)
    {
        $binaan = $mode === self::MODE_BINAAN;

        $results = [
            'valid' => [],
            'errors' => [],
            'duplicates' => [],
            'total' => count($rows),
        ];

        // Fetch all existing emails to fast-check duplicates
        $existingEmails = User::pluck('email')
            ->filter()
            ->map(fn ($email) => strtolower((string) $email))
            ->all();

        $emailDalamFile = [];

        foreach ($rows as $row) {
            $rowNum = $row['_row_number'] ?? null;
            $errors = [];
            $parsedData = [];

            // 0. Baris CONTOH template yang lupa dihapus.
            if (! empty($row['_contoh'])) {
                $errors[] = [
                    'col' => 'Baris Contoh',
                    'val' => SpesifikasiKolomImport::PENANDA_CONTOH,
                    'msg' => 'Baris contoh pada template masih ada. Hapus baris bertanda '
                        .'"CONTOH - HAPUS SEBELUM IMPORT", lalu unggah ulang.',
                ];
            }

            // 1. Nama (wajib di semua mode)
            $nama = trim((string) ($row['nama'] ?? ''));

            if ($nama === '') {
                $errors[] = ['col' => 'Nama', 'val' => '', 'msg' => 'Nama wajib diisi'];
            } elseif (mb_strlen($nama) > 150) {
                $errors[] = ['col' => 'Nama', 'val' => $nama, 'msg' => 'Nama maksimal 150 karakter'];
            } else {
                $parsedData['name'] = $nama;
            }

            // 2. Email (wajib di mode klien; opsional di mode binaan)
            $email = trim((string) ($row['email'] ?? ''));

            if ($email === '') {
                if (! $binaan) {
                    $errors[] = ['col' => 'Email', 'val' => '', 'msg' => 'Email wajib diisi'];
                }

                $parsedData['email'] = null;
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['col' => 'Email', 'val' => $email, 'msg' => 'Format email tidak valid'];
                $parsedData['email'] = null;
            } else {
                $kunciEmail = strtolower($email);

                if (in_array($kunciEmail, $existingEmails, true) || isset($emailDalamFile[$kunciEmail])) {
                    $results['duplicates'][] = [
                        'row' => $rowNum,
                        'blok' => $row['_blok'] ?? null,
                        'name' => $nama,
                        'email' => $email,
                    ];

                    $emailDalamFile[$kunciEmail] = true;

                    continue; // baris duplikat tidak diimpor
                }

                $emailDalamFile[$kunciEmail] = true;
                $parsedData['email'] = $email;
            }

            // 3. Jenis Kelamin (L/P)
            $jk = strtoupper(trim((string) ($row['jenis kelamin'] ?? '')));

            if ($jk === '') {
                if ($binaan) {
                    $errors[] = ['col' => 'Jenis Kelamin', 'val' => '', 'msg' => 'Jenis kelamin wajib diisi (L/P)'];
                }
            } elseif (! in_array($jk, ['L', 'P'], true)) {
                $errors[] = ['col' => 'Jenis Kelamin', 'val' => $jk, 'msg' => 'Harus L atau P'];
            }

            $parsedData['gender'] = in_array($jk, ['L', 'P'], true) ? $jk : null;

            // 4. Asal Daerah (wajib mode binaan)
            $asalDaerah = $this->teksOpsional($row['asal daerah'] ?? null);

            if ($binaan && $asalDaerah === null) {
                $errors[] = ['col' => 'Asal Daerah', 'val' => '', 'msg' => 'Asal daerah wajib diisi'];
            } elseif ($asalDaerah !== null && mb_strlen($asalDaerah) > 100) {
                $errors[] = ['col' => 'Asal Daerah', 'val' => $asalDaerah, 'msg' => 'Asal daerah maksimal 100 karakter'];
            }

            $parsedData['asal_daerah'] = $asalDaerah;

            // 5. Agama (wajib mode binaan)
            $agama = $this->teksOpsional($row['agama'] ?? null);

            if ($binaan && $agama === null) {
                $errors[] = ['col' => 'Agama', 'val' => '', 'msg' => 'Agama wajib diisi'];
            } elseif ($agama !== null && mb_strlen($agama) > 50) {
                $errors[] = ['col' => 'Agama', 'val' => $agama, 'msg' => 'Agama maksimal 50 karakter'];
            }

            $parsedData['agama'] = $agama;

            // 6. Umur & 7. Tanggal Lahir (salah satu wajib pada mode binaan).
            $tanggalLahir = $this->parseTanggal($row['tanggal lahir'] ?? null);

            if ($tanggalLahir === false) {
                $errors[] = [
                    'col' => 'Tanggal Lahir',
                    'val' => $this->teksOpsional($row['tanggal lahir'] ?? null) ?? '',
                    'msg' => 'Format tanggal tidak valid. Gunakan DD/MM/YYYY (contoh 17/08/2008)',
                ];
                $tanggalLahir = null;
            }

            $parsedData['birth_date'] = $tanggalLahir;

            $umur = $this->parseUmur($row['umur'] ?? null);

            if ($umur === null && $tanggalLahir !== null) {
                $umur = $this->hitungUmur($tanggalLahir);
            }

            if ($umur === null) {
                if ($binaan) {
                    $errors[] = ['col' => 'Umur', 'val' => '', 'msg' => 'Umur wajib diisi (5-30) atau isi Tanggal Lahir'];
                }
            } elseif ($umur < 5 || $umur > 30) {
                if ($binaan) {
                    $errors[] = ['col' => 'Umur', 'val' => (string) $umur, 'msg' => 'Umur harus antara 5 sampai 30 tahun'];
                } else {
                    $umur = null;
                }
            }

            $parsedData['umur'] = $umur;

            // 8. Angkatan (wajib mode binaan)
            $angkatan = $this->teksOpsional($row['angkatan'] ?? null);

            if ($binaan && $angkatan === null) {
                $errors[] = ['col' => 'Angkatan', 'val' => '', 'msg' => 'Angkatan wajib diisi'];
            } elseif ($angkatan !== null && mb_strlen($angkatan) > 50) {
                $errors[] = ['col' => 'Angkatan', 'val' => $angkatan, 'msg' => 'Angkatan maksimal 50 karakter'];
            }

            $parsedData['angkatan'] = $angkatan;

            // 9. Tempat Lahir
            $parsedData['birth_place'] = $this->teksOpsional($row['tempat lahir'] ?? null);

            // 10. Alamat KTP
            $parsedData['ktp_address'] = $this->teksOpsional($row['alamat ktp'] ?? null);

            // 11. Kota KTP
            $kotaKtp = trim((string) ($row['kota ktp'] ?? ''));

            if ($kotaKtp !== '') {
                $kotaKey = strtolower($kotaKtp);

                if (isset($this->cities[$kotaKey])) {
                    $parsedData['ktp_city_id'] = $this->cities[$kotaKey];
                } else {
                    $errors[] = ['col' => 'Kota KTP', 'val' => $kotaKtp, 'msg' => 'Data kota tidak ditemukan di sistem'];
                }
            }

            // 12. Pendidikan
            $pendidikan = trim((string) ($row['pendidikan'] ?? ''));

            if ($pendidikan !== '') {
                $pendidikanKey = strtolower($pendidikan);

                if (isset($this->educations[$pendidikanKey])) {
                    $parsedData['education_degree_id'] = $this->educations[$pendidikanKey];
                } else {
                    $errors[] = ['col' => 'Pendidikan', 'val' => $pendidikan, 'msg' => 'Data pendidikan tidak ditemukan di sistem'];
                }
            }

            // 13. Pelatihan (boleh lebih dari satu, dipisah koma)
            $parsedData['trainings'] = [];
            $pelatihan = trim((string) ($row['pelatihan'] ?? ''));

            if ($pelatihan !== '') {
                foreach (array_map('trim', explode(',', $pelatihan)) as $item) {
                    if ($item === '') {
                        continue;
                    }

                    $itemKey = strtolower($item);

                    if (isset($this->trainings[$itemKey])) {
                        $parsedData['trainings'][] = $this->trainings[$itemKey];
                    } else {
                        $errors[] = ['col' => 'Pelatihan', 'val' => $item, 'msg' => 'Pelatihan tidak ditemukan di sistem'];
                    }
                }
            }

            // 14. Penempatan Wilayah (boleh lebih dari satu, dipisah koma)
            $parsedData['work_locations'] = [];
            $wilayah = trim((string) ($row['penempatan wilayah'] ?? ''));

            if ($wilayah !== '') {
                foreach (array_map('trim', explode(',', $wilayah)) as $item) {
                    if ($item === '') {
                        continue;
                    }

                    $itemKey = strtolower($item);

                    if (isset($this->cities[$itemKey])) {
                        $parsedData['work_locations'][] = $this->cities[$itemKey];
                    } else {
                        $errors[] = ['col' => 'Penempatan Wilayah', 'val' => $item, 'msg' => 'Wilayah tidak ditemukan di sistem'];
                    }
                }
            }

            // 15. Status Pekerjaan
            $statusKerja = strtolower(trim((string) ($row['status pekerjaan'] ?? '')));
            $parsedData['is_current_job'] = ($statusKerja === 'sedang bekerja' || $statusKerja === '1');

            // 16. Deskripsi
            $parsedData['description'] = $this->teksOpsional($row['deskripsi'] ?? null);

            // 17. Status Profil
            $statusProfil = strtolower(trim((string) ($row['status profil'] ?? '')));
            $parsedData['hire_status'] = ($statusProfil === 'tersedia' || $statusProfil === '1') ? '1' : '0';

            // 18. Nama File Foto
            $parsedData['photo_filename'] = trim((string) ($row['nama file foto'] ?? ''));

            // Compile errors or valid data
            if ($errors !== []) {
                foreach ($errors as $err) {
                    $results['errors'][] = [
                        'row' => $rowNum,
                        'blok' => $row['_blok'] ?? null,
                        'name' => $nama,
                        'col' => $err['col'],
                        'val' => $err['val'],
                        'msg' => $err['msg'],
                    ];
                }
            } else {
                $parsedData['_row_number'] = $rowNum;
                $parsedData['_blok'] = $row['_blok'] ?? null;
                $results['valid'][] = $parsedData;
            }
        }

        return $results;
    }

    /**
     * Normalisasi tanggal dari Excel/Word menjadi `Y-m-d`.
     *
     * @return string|false|null Null bila kosong, false bila format tidak valid.
     */
    protected function parseTanggal(mixed $nilai): string|false|null
    {
        if ($nilai === null || trim((string) $nilai) === '') {
            return null;
        }

        if (is_numeric($nilai)) {
            // Excel serialized date (bisa berupa float).
            $serial = (float) $nilai;

            if ($serial <= 0) {
                return false;
            }

            return gmdate('Y-m-d', (int) round(($serial - 25569) * 86400));
        }

        $teks = trim((string) $nilai);

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y'] as $format) {
            $tanggal = \DateTimeImmutable::createFromFormat('!'.$format, $teks);
            $kesalahan = \DateTimeImmutable::getLastErrors();

            $valid = $tanggal !== false
                && ($kesalahan === false || ($kesalahan['warning_count'] === 0 && $kesalahan['error_count'] === 0))
                && $tanggal->format($format) === $teks;

            if ($valid) {
                return $tanggal->format('Y-m-d');
            }
        }

        return false;
    }

    /**
     * Umur dari file (bilangan bulat), atau null bila kosong/bukan angka.
     */
    protected function parseUmur(mixed $nilai): ?int
    {
        $teks = trim((string) $nilai);

        if ($teks === '' || preg_match('/^\d+$/', $teks) !== 1) {
            return null;
        }

        return (int) $teks;
    }

    /**
     * Hitung umur (tahun penuh) dari tanggal lahir `Y-m-d`.
     */
    protected function hitungUmur(string $tanggalLahir): ?int
    {
        try {
            $lahir = new \DateTimeImmutable($tanggalLahir);
        } catch (\Throwable) {
            return null;
        }

        return $lahir->diff(new \DateTimeImmutable('today'))->y;
    }

    /**
     * Cek kata sandi awal import terhadap hash di IMPORT_PASSWORD_HASH.
     *
     * @param  string|null  $password  Kata sandi yang diketik operator (opsional).
     * @return bool True bila kosong (tidak memakai kata sandi awal) atau cocok.
     */
    public static function initialPasswordMatches(?string $password): bool
    {
        if (blank($password)) {
            return true;
        }

        $hash = config('pendekar.import.password_hash');

        if (blank($hash)) {
            return false;
        }

        return Hash::check($password, $hash);
    }

    /**
     * Simpan hasil preview menjadi data anak + akun (sesuai mode).
     *
     * @param  array<int, array<string, mixed>>  $validData
     * @param  array<int, string>  $uploadedPhotos
     * @param  int|null  $createdBy  Pemilik data (id admin yang mengimpor).
     * @param  string|null  $initialPassword  Kata sandi awal opsional (mode klien).
     * @param  string  $mode  `binaan` (anak + akun Draft) atau `klien` (akun aktif).
     * @param  bool  $tautkanAnak  Hanya mode binaan: tautkan ke anak yang sudah ada.
     * @param  bool  $imporGanda  Hanya mode binaan: tetap impor baris yang terdeteksi ganda.
     * @return array{mode: string, anak_baru: int, ditautkan: int, akun_dibuat: int, gagal: int, gagal_detail: array<int, array<string, mixed>>, dilewati: int, dilewati_detail: array<int, array<string, mixed>>}
     */
    public function importData(array $validData, array $uploadedPhotos = [], ?int $createdBy = null, ?string $initialPassword = null, string $mode = self::MODE_KLIEN, bool $tautkanAnak = false, bool $imporGanda = false): array
    {
        $ganda = [];

        // Baris dengan identitas yang SAMA PERSIS (nama + alamat KTP + tanggal
        // lahir + asal daerah + angkatan) dilewati agar data tidak tercatat dua
        // kali. Operator dapat memaksa impor lewat checkbox "Impor baris ganda".
        if ($mode === self::MODE_BINAAN && $validData !== []) {
            $analisis = $this->analisisDuplikatBinaan($validData);
            $ganda = $analisis['pasti'];

            if (! $imporGanda && $ganda !== []) {
                $nomor = array_flip(array_map(
                    fn (array $entri) => (string) $entri['row'],
                    $ganda
                ));

                $validData = array_values(array_filter(
                    $validData,
                    fn (array $baris) => ! isset($nomor[(string) ($baris['_row_number'] ?? '')])
                ));
            }

            Log::warning('import.binaan.duplikat_identitas', [
                'created_by' => $createdBy,
                'jumlah' => count($ganda),
                'dilewati' => $imporGanda ? 0 : count($ganda),
            ]);
        }

        $ringkasan = $mode === self::MODE_BINAAN
            ? $this->importBinaan($validData, $uploadedPhotos, $createdBy, $tautkanAnak)
            : $this->importKlien($validData, $uploadedPhotos, $createdBy, $initialPassword);

        $ringkasan['dilewati'] = $imporGanda ? 0 : count($ganda);
        $ringkasan['dilewati_detail'] = $imporGanda ? [] : $ganda;

        return $ringkasan;
    }

    /**
     * Mode Klien Umum: satu baris = satu akun `Published` yang bisa login.
     *
     * @param  array<int, array<string, mixed>>  $validData
     * @param  array<int, string>  $uploadedPhotos
     * @return array{mode: string, anak_baru: int, ditautkan: int, akun_dibuat: int, gagal: int, gagal_detail: array<int, array<string, mixed>>}
     */
    protected function importKlien(array $validData, array $uploadedPhotos, ?int $createdBy, ?string $initialPassword): array
    {
        // SECURITY (FASE 3): tidak ada kata sandi default yang diketahui
        // publik. Tanpa kata sandi awal, peserta tidak bisa login sampai
        // melakukan reset password.
        $plainPassword = filled($initialPassword)
            ? $initialPassword
            : Str::password((int) config('pendekar.import.password_length', 32));

        $ringkasan = $this->ringkasanKosong(self::MODE_KLIEN);

        DB::beginTransaction();

        try {
            foreach ($validData as $data) {
                $atributUser = [
                    'name' => $data['name'],
                    'email' => $data['email'] ?? null,
                    'password' => $plainPassword,
                    'user_type' => User::ROLE_CLIENT,
                    'status' => UserStatus::Published,
                    'created_by' => $createdBy,
                ];

                // Kolom `anak_id` hanya dikirim bila kolomnya ada; menyertakan
                // kolom yang belum dimigrasikan membuat INSERT gagal (500).
                if (SchemaGuard::usersPunyaTautanAnak()) {
                    $atributUser['anak_id'] = null;
                }

                $user = User::create($atributUser);

                // user_id diisi otomatis oleh relasi (bukan mass assignment).
                $user->userProfile()->create(
                    $this->profilDariBaris($data, $this->cocokkanFoto($data, $uploadedPhotos))
                );

                if (! empty($data['trainings'])) {
                    $user->userTrainings()->attach($data['trainings']);
                }

                if (! empty($data['work_locations'])) {
                    $user->userWorkLocations()->attach($data['work_locations']);
                }

                if (! empty($data['is_current_job'])) {
                    $user->userExperiences()->create([
                        'experience_job_name' => 'Bekerja Saat Ini',
                        'experience_description' => 'Di-import dari sistem',
                        'is_current_job' => true,
                        'sort_order' => 1,
                    ]);
                }

                $ringkasan['akun_dibuat']++;
            }

            DB::commit();

            // Jejak audit: siapa mengimpor berapa baris, dengan mode apa.
            Log::info('import.peserta', [
                'actor_id' => $createdBy,
                'rows' => count($validData),
                'mode' => self::MODE_KLIEN,
                'pakai_kata_sandi_awal' => filled($initialPassword),
            ]);

            return $ringkasan;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('import.gagal', [
                'mode' => self::MODE_KLIEN,
                'error' => get_class($e),
            ]);

            throw $e;
        }
    }

    /**
     * Mode Remaja Binaan: per baris buat data anak (status Aktif) lalu akun
     * klien `Draft` yang tertaut ke anak tersebut.
     *
     * Bila operator mencentang "Tautkan ke anak yang sudah ada", baris yang
     * cocok (nama unik, asal daerah/angkatan tidak bertentangan, anak belum
     * punya akun) HANYA membuat akun Draft tanpa anak baru.
     *
     * KOMPENSASI: bila pembuatan akun gagal setelah anak dibuat, anak yang
     * baru dibuat pada baris itu dihapus lagi (tidak ada anak tanpa akun).
     *
     * @param  array<int, array<string, mixed>>  $validData
     * @param  array<int, string>  $uploadedPhotos
     * @return array{mode: string, anak_baru: int, ditautkan: int, akun_dibuat: int, gagal: int, gagal_detail: array<int, array<string, mixed>>}
     */
    protected function importBinaan(array $validData, array $uploadedPhotos, ?int $createdBy, bool $tautkanAnak): array
    {
        // PENGAMAN SKEMA (lihat §17): tanpa kolom `users.anak_id` tidak ada akun
        // yang bisa ditautkan ke anak. Import tetap dijalankan seperti perilaku
        // Fase 3 (akun Draft saja) agar aplikasi tetap bisa dipakai sebelum
        // migrasi dijalankan; anak baru dibuat setelah migrasi tersedia.
        if (! SchemaGuard::usersPunyaTautanAnak()) {
            return $this->importBinaanTanpaTautan($validData, $uploadedPhotos, $createdBy);
        }

        $binaan = new ImportBinaanService;

        // Peta tautan dihitung dengan fungsi yang sama seperti langkah Preview,
        // dikunci per nomor baris supaya baris tanpa email tetap bisa ditautkan.
        $peta = $tautkanAnak ? $this->petaTautanAnak($validData, true)['peta'] : [];

        $ringkasan = $this->ringkasanKosong(self::MODE_BINAAN);

        foreach ($validData as $data) {
            $row = $data['_row_number'] ?? null;
            $nama = (string) ($data['name'] ?? '');
            $anak = null;
            $anakBaru = false;

            try {
                $anakId = $peta[(string) $row] ?? null;

                if ($anakId !== null) {
                    $anak = Anak::query()->find((string) $anakId);

                    if ($anak === null) {
                        throw new \RuntimeException('Data anak yang akan ditautkan tidak ditemukan.');
                    }

                    if (SchemaGuard::usersPunyaTautanAnak()
                        && User::query()->where('anak_id', $anak->getKey())->exists()) {
                        throw new \RuntimeException('Anak sudah punya akun klien.');
                    }
                } else {
                    $anak = $binaan->buatAnakBaru([
                        'nama' => $nama,
                        'jenis_kelamin' => $this->jenisKelaminAnak($data['gender'] ?? null),
                        'asal_daerah' => (string) ($data['asal_daerah'] ?? ''),
                        'agama' => (string) ($data['agama'] ?? ''),
                        'umur' => (int) ($data['umur'] ?? 0),
                        'angkatan' => (string) ($data['angkatan'] ?? ''),
                        'status' => Anak::STATUS_AKTIF,
                    ]);

                    $anakBaru = true;
                }

                $binaan->buatAkunDraft(
                    $anak,
                    $createdBy,
                    $data['email'] ?? null,
                    $this->profilDariBaris($data, $this->cocokkanFoto($data, $uploadedPhotos)),
                    $data['trainings'] ?? [],
                    $data['work_locations'] ?? [],
                    ! empty($data['is_current_job']),
                );

                $anakBaru ? $ringkasan['anak_baru']++ : $ringkasan['ditautkan']++;
                $ringkasan['akun_dibuat']++;
            } catch (\Throwable $e) {
                if ($anakBaru && $anak instanceof Anak) {
                    $binaan->hapusAnakBaru($anak);
                }

                $ringkasan['gagal']++;
                $ringkasan['gagal_detail'][] = [
                    'row' => $row,
                    'blok' => $data['_blok'] ?? null,
                    'nama' => $nama,
                    'alasan' => $e->getMessage(),
                ];

                // Log tanpa PII: nomor baris + kelas error saja.
                Log::error('import.binaan.baris_gagal', [
                    'row' => $row,
                    'error' => get_class($e),
                    'created_by' => $createdBy,
                ]);
            }
        }

        Log::info('import.binaan.ringkasan', [
            'created_by' => $createdBy,
            'akun_dibuat' => $ringkasan['akun_dibuat'],
            'anak_baru' => $ringkasan['anak_baru'],
            'ditautkan' => $ringkasan['ditautkan'],
            'gagal' => $ringkasan['gagal'],
            'tautkan_anak' => $tautkanAnak,
        ]);

        return $ringkasan;
    }

    /**
     * Mode binaan pada database yang belum dimigrasi (`users.anak_id` belum ada).
     *
     * Tidak ada data anak yang dibuat karena akun tidak mungkin ditautkan;
     * setelah migrasi dijalankan operator dapat mengulang import (baris lama
     * belum punya anak, sehingga tidak menghasilkan data ganda).
     *
     * @param  array<int, array<string, mixed>>  $validData
     * @param  array<int, string>  $uploadedPhotos
     * @return array{mode: string, anak_baru: int, ditautkan: int, akun_dibuat: int, gagal: int, gagal_detail: array<int, array<string, mixed>>}
     */
    protected function importBinaanTanpaTautan(array $validData, array $uploadedPhotos, ?int $createdBy): array
    {
        $ringkasan = $this->ringkasanKosong(self::MODE_BINAAN);

        foreach ($validData as $data) {
            $row = $data['_row_number'] ?? null;

            try {
                $user = User::create([
                    'name' => (string) ($data['name'] ?? ''),
                    'email' => filled($data['email'] ?? null) ? (string) $data['email'] : null,
                    'password' => null,
                    'user_type' => User::ROLE_CLIENT,
                    'status' => UserStatus::Draft,
                    'created_by' => $createdBy,
                ]);

                $user->userProfile()->create(
                    $this->profilDariBaris($data, $this->cocokkanFoto($data, $uploadedPhotos))
                );

                if (! empty($data['trainings'])) {
                    $user->userTrainings()->attach(array_values(array_unique(array_map('intval', $data['trainings']))));
                }

                if (! empty($data['work_locations'])) {
                    $user->userWorkLocations()->attach(array_values(array_unique(array_map('intval', $data['work_locations']))));
                }

                $ringkasan['akun_dibuat']++;
            } catch (\Throwable $e) {
                $ringkasan['gagal']++;
                $ringkasan['gagal_detail'][] = [
                    'row' => $row,
                    'blok' => $data['_blok'] ?? null,
                    'nama' => (string) ($data['name'] ?? ''),
                    'alasan' => $e->getMessage(),
                ];

                Log::error('import.binaan.baris_gagal', [
                    'row' => $row,
                    'error' => get_class($e),
                    'created_by' => $createdBy,
                    'schema_fase2' => false,
                ]);
            }
        }

        Log::warning('import.binaan.tanpa_kolom_anak', [
            'created_by' => $createdBy,
            'akun_dibuat' => $ringkasan['akun_dibuat'],
            'gagal' => $ringkasan['gagal'],
        ]);

        return $ringkasan;
    }

    /**
     * Struktur ringkasan hasil import.
     *
     * @return array{mode: string, anak_baru: int, ditautkan: int, akun_dibuat: int, gagal: int, gagal_detail: array<int, array<string, mixed>>, dilewati: int, dilewati_detail: array<int, array<string, mixed>>}
     */
    protected function ringkasanKosong(string $mode): array
    {
        return [
            'mode' => $mode,
            'anak_baru' => 0,
            'ditautkan' => 0,
            'akun_dibuat' => 0,
            'gagal' => 0,
            'gagal_detail' => [],
            'dilewati' => 0,
            'dilewati_detail' => [],
        ];
    }

    /**
     * Data profil yang disimpan untuk setiap akun hasil import.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function profilDariBaris(array $data, ?string $photoPath): array
    {
        return [
            'gender' => $data['gender'] ?? null,
            'birth_place' => $data['birth_place'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'ktp_address' => $data['ktp_address'] ?? null,
            'ktp_city_id' => $data['ktp_city_id'] ?? null,
            'education_degree_id' => $data['education_degree_id'] ?? null,
            'description' => $data['description'] ?? null,
            'hire_status' => $data['hire_status'] ?? '0',
            'profile_picture_path' => $photoPath,
        ];
    }

    /**
     * Cocokkan kolom "Nama File Foto" dengan foto yang diunggah operator.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $uploadedPhotos
     */
    protected function cocokkanFoto(array $data, array $uploadedPhotos): ?string
    {
        $namaFile = strtolower(trim((string) ($data['photo_filename'] ?? '')));

        if ($namaFile === '' || $uploadedPhotos === []) {
            return null;
        }

        foreach ($uploadedPhotos as $foto) {
            if (strtolower(basename((string) $foto)) === $namaFile) {
                return (string) $foto;
            }
        }

        return null;
    }

    /**
     * Kode L/P pada file → nilai kolom `anak.jenis_kelamin` (SEMARAK).
     */
    protected function jenisKelaminAnak(?string $kode): string
    {
        return match (strtoupper(trim((string) $kode))) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => throw new \InvalidArgumentException('Jenis kelamin harus L atau P.'),
        };
    }

    /**
     * Rencana penautan akun ke `semarak.anak` SEBELUM impor.
     *
     * Dipakai dua kali dengan aturan identik, sehingga yang ditautkan saat
     * impor persis sama dengan yang dilihat operator di langkah Preview:
     * - `parseAndValidate()` → ditampilkan di Preview;
     * - `importData(..., tautkanAnak: true)` → nilai `peta` yang dipakai.
     *
     * Aturan keamanan data (checkbox "Tautkan ke anak yang sudah ada"):
     * - Hanya nama yang PERSIS sama (tanpa beda kapital/spasi) yang dihitung.
     * - Nama kembar (lebih dari satu anak memakai nama itu) TIDAK PERNAH
     *   ditautkan, meski operator mencentang opsi.
     * - Nama yang muncul lebih dari sekali di file juga tidak ditautkan.
     * - Asal daerah/angkatan di file tidak boleh BERTENTANGAN dengan data anak.
     * - Anak yang sudah punya akun klien tidak ditautkan (unique `users.anak_id`).
     * - Bila koneksi `semarak` tidak terbaca, impor tetap jalan: semua baris
     *   membuat anak baru (dicatat sebagai warning).
     *
     * @param  array<int, array<string, mixed>>  $validData
     * @return array{akan: array<int, array<string, mixed>>, tidak: array<int, array<string, mixed>>, peta: array<string, string>, rencana: array<int, array<string, mixed>>}
     */
    public function petaTautanAnak(array $validData, bool $tautkan = false): array
    {
        $hasil = ['akan' => [], 'tidak' => [], 'peta' => [], 'rencana' => []];

        // PENGAMAN SKEMA: tanpa `users.anak_id` (migrasi Fase 2 tertunda)
        // belum ada tautan yang mungkin dibuat. Semua baris tetap membuat anak
        // baru + akun Draft, dengan keterangan yang bisa dibaca operator.
        $tautanDimungkinkan = SchemaGuard::usersPunyaTautanAnak();

        if (! $tautkan || ! $tautanDimungkinkan) {
            foreach ($validData as $baris) {
                $nama = trim((string) ($baris['name'] ?? ''));

                if ($nama === '') {
                    continue;
                }

                // Alasan migrasi selalu dilaporkan (walau operator tidak
                // mencentang tautkan) karena pada kondisi itu penautan memang
                // tidak mungkin dan operator perlu tahu.
                $alasan = $tautanDimungkinkan
                    ? null
                    : 'Migrasi database belum dijalankan (kolom users.anak_id belum ada); anak baru tetap dibuat.';

                $hasil['rencana'][] = [
                    'row' => $baris['_row_number'] ?? null,
                    'nama' => $nama,
                    'email' => $baris['email'] ?? null,
                    'aksi' => 'anak_baru',
                    'anak_id' => null,
                    'keterangan' => $alasan ?? 'Anak baru (status Aktif) + akun Draft',
                ];

                if ($alasan !== null) {
                    $hasil['tidak'][] = [
                        'row' => $baris['_row_number'] ?? null,
                        'nama' => $nama,
                        'email' => trim((string) ($baris['email'] ?? '')),
                        'alasan' => $alasan,
                    ];
                }
            }

            return $hasil;
        }

        $namaBaris = [];

        foreach ($validData as $baris) {
            $nama = $this->normalkanNama($baris['name'] ?? null);

            if ($nama !== '') {
                $namaBaris[] = $nama;
            }
        }

        if ($namaBaris === []) {
            return $hasil;
        }

        $kemunculan = array_count_values($namaBaris);
        $kandidat = $this->kandidatTautan(array_keys($kemunculan));
        $sudahBerakun = [];

        foreach ($kandidat as $grup) {
            foreach ($grup as $calon) {
                if ($calon['akun_id'] !== null) {
                    $sudahBerakun[(string) $calon['id']] = true;
                }
            }
        }

        foreach ($validData as $baris) {
            $nama = trim((string) ($baris['name'] ?? ''));
            $kunci = $this->normalkanNama($nama);
            $email = trim((string) ($baris['email'] ?? ''));
            $row = $baris['_row_number'] ?? null;

            if ($kunci === '') {
                continue;
            }

            $calon = $kandidat->get($kunci, collect());
            $alasan = null;
            $anak = null;

            if (($kemunculan[$kunci] ?? 0) > 1) {
                $alasan = 'Nama ini muncul lebih dari sekali di file';
            } elseif ($calon->isEmpty()) {
                $alasan = 'Tidak ada data anak dengan nama ini';
            } elseif ($calon->count() > 1) {
                // Nama kembar: hanya ditautkan bila identitas lengkap menunjuk
                // TEPAT SATU anak (alamat KTP + tanggal lahir + asal + angkatan).
                $cocok = $calon->filter(fn (array $k) => $this->identitasCocok($baris, $k))->values();

                if ($cocok->count() === 1) {
                    /** @var array<string, mixed> $terpilih */
                    $terpilih = $cocok->first();

                    if (isset($sudahBerakun[(string) $terpilih['id']])) {
                        $alasan = 'Anak '.$terpilih['id'].' sudah punya akun klien';
                    } else {
                        $anak = $terpilih;
                    }
                } else {
                    $alasan = 'Nama kembar: '.$calon->count().' anak memakai nama ini'
                        .($cocok->isEmpty()
                            ? '; alamat/tanggal lahir tidak dapat dipastikan'
                            : '; alamat/tanggal lahir tidak menunjuk tepat satu anak');
                }
            } else {
                /** @var array<string, mixed> $terpilih */
                $terpilih = $calon->first();

                if (isset($sudahBerakun[(string) $terpilih['id']])) {
                    $alasan = 'Anak '.$terpilih['id'].' sudah punya akun klien';
                } else {
                    $alasan = $this->konflikIdentitasKandidat($baris, $terpilih);
                    $anak = $alasan === null ? $terpilih : null;
                }
            }

            if ($anak === null) {
                $hasil['tidak'][] = [
                    'row' => $row,
                    'nama' => $nama,
                    'email' => $email,
                    'alasan' => (string) $alasan,
                ];
                $hasil['rencana'][] = [
                    'row' => $row,
                    'nama' => $nama,
                    'email' => $email,
                    'aksi' => 'anak_baru',
                    'anak_id' => null,
                    'keterangan' => 'Anak baru + akun Draft ('.(string) $alasan.')',
                ];

                continue;
            }

            $hasil['akan'][] = [
                'row' => $row,
                'nama' => $nama,
                'email' => $email,
                'anak_id' => (string) $anak['id'],
                'asal_daerah' => $anak['asal'],
                'angkatan' => $anak['angkatan'],
                'status' => $anak['status'],
            ];
            $hasil['peta'][(string) $row] = (string) $anak['id'];
            $hasil['rencana'][] = [
                'row' => $row,
                'nama' => $nama,
                'email' => $email,
                'aksi' => 'tautkan',
                'anak_id' => (string) $anak['id'],
                'keterangan' => 'Tautkan ke '.$anak['id'].' (akun Draft, tanpa anak baru)',
            ];
        }

        return $hasil;
    }

    /**
     * Identitas di file (alamat KTP/tanggal lahir/asal/angkatan) bertentangan
     * dengan kandidat anak yang namanya tunggal? Bila ya, jangan tautkan:
     * kemungkinan besar orang lain yang kebetulan bernama sama.
     *
     * @param  array<string, mixed>  $baris
     * @param  array<string, mixed>  $calon
     */
    protected function konflikIdentitasKandidat(array $baris, array $calon): ?string
    {
        $label = (string) ($calon['label'] ?? $calon['id'] ?? 'anak');

        $asalFile = $this->teksOpsional($baris['asal_daerah'] ?? null);
        $angkatanFile = $this->teksOpsional($baris['angkatan'] ?? null);
        $alamatFile = $this->teksOpsional($baris['ktp_address'] ?? null);
        $lahirFile = $this->teksOpsional($baris['birth_date'] ?? null);

        if ($asalFile !== null && $this->normalkanNama($asalFile) !== $this->normalkanNama($calon['asal'] ?? null)) {
            return 'Asal daerah berbeda dengan data anak '.$label;
        }

        if ($angkatanFile !== null && $this->normalkanNama($angkatanFile) !== $this->normalkanNama($calon['angkatan'] ?? null)) {
            return 'Angkatan berbeda dengan data anak '.$label;
        }

        if ($alamatFile !== null && filled($calon['alamat'] ?? null)
            && $this->normalkanNama($alamatFile) !== $this->normalkanNama($calon['alamat'])) {
            return 'Alamat KTP berbeda dengan data anak '.$label;
        }

        if ($lahirFile !== null && filled($calon['lahir'] ?? null) && $lahirFile !== $calon['lahir']) {
            return 'Tanggal lahir berbeda dengan data anak '.$label;
        }

        return null;
    }

    /**
     * Identitas lengkap file cocok dengan kandidat? Dipakai untuk memilih tepat
     * satu anak ketika namanya kembar. Alamat KTP wajib sama (tidak boleh
     * kosong di salah satu sisi); tanggal lahir dibandingkan bila keduanya ada.
     *
     * @param  array<string, mixed>  $baris
     * @param  array<string, mixed>  $calon
     */
    protected function identitasCocok(array $baris, array $calon): bool
    {
        $alamatFile = $this->normalkanNama($baris['ktp_address'] ?? null);
        $alamatCalon = $this->normalkanNama($calon['alamat'] ?? null);

        if ($alamatFile === '' || $alamatCalon === '' || $alamatFile !== $alamatCalon) {
            return false;
        }

        $asalFile = $this->normalkanNama($baris['asal_daerah'] ?? null);
        $angkatanFile = $this->normalkanNama($baris['angkatan'] ?? null);

        if ($asalFile === '' || $angkatanFile === ''
            || $asalFile !== $this->normalkanNama($calon['asal'] ?? null)
            || $angkatanFile !== $this->normalkanNama($calon['angkatan'] ?? null)) {
            return false;
        }

        $lahirFile = $this->teksOpsional($baris['birth_date'] ?? null);
        $lahirCalon = $this->teksOpsional($calon['lahir'] ?? null);

        return $lahirFile === null || $lahirCalon === null || $lahirFile === $lahirCalon;
    }

    /**
     * Analisis identitas baris mode binaan.
     *
     * Kunci identitas: nama + alamat KTP + tanggal lahir (bila ada) + asal
     * daerah + angkatan. Hasil dibagi tiga:
     *
     * - `pasti`      : identitas sama persis dengan data anak/akun lama, atau
     *                  dengan baris lain di file yang sama. Baris dilewati saat
     *                  impor kecuali operator mencentang "Impor baris ganda".
     * - `kemungkinan`: nama sama tetapi identitas belum bisa dipastikan
     *                  (alamat kosong di salah satu sisi) → peringatan, tetap
     *                  diimpor.
     * - `informasi`  : nama sama tetapi alamat/tanggal lahir/asal/angkatan
     *                  berbeda → dianggap orang lain, tetap diimpor dan TIDAK
     *                  dilaporkan sebagai peringatan (tidak masuk `kemungkinan`).
     *
     * Nama tidak pernah dipakai sendirian sebagai penentu duplikat, sehingga
     * dua anak bernama sama tetap dapat diimpor.
     *
     * @param  array<int, array<string, mixed>>  $validData
     * @return array{pasti: array<int, array<string, mixed>>, kemungkinan: array<int, array<string, mixed>>, dilewati: array<int, int|string|null>}
     */
    public function analisisDuplikatBinaan(array $validData): array
    {
        $hasil = ['pasti' => [], 'kemungkinan' => [], 'dilewati' => []];

        if ($validData === []) {
            return $hasil;
        }

        $nama = array_values(array_filter(array_map(
            fn (array $baris) => $this->normalkanNama($baris['name'] ?? null),
            $validData
        )));

        $kandidat = $this->kandidatIdentitas($nama);

        /** @var array<int, array<string, mixed>> $pasti */
        $pasti = [];
        /** @var array<int, array<string, mixed>> $kemungkinan */
        $kemungkinan = [];

        // 1. Bandingkan dengan data anak/akun yang sudah ada.
        foreach ($validData as $index => $baris) {
            $kunci = $this->normalkanNama($baris['name'] ?? null);

            if ($kunci === '') {
                continue;
            }

            foreach ($kandidat[$kunci] ?? [] as $calon) {
                $nilai = $this->bandingkanIdentitas($baris, $calon);

                if ($nilai['jenis'] === 'pasti') {
                    $pasti[$index] = $this->entriPasti($baris, $calon, $nilai['keterangan']);

                    break;
                }

                if ($nilai['jenis'] === 'peringatan') {
                    $kemungkinan[$index] ??= $this->entriKemungkinan($baris, $calon, $nilai);
                }
            }
        }

        // 2. Bandingkan antar-baris di file. Baris pertama dianggap asli;
        //    baris berikutnya ganda bila identitasnya lengkap dan sama.
        $perNama = [];

        foreach ($validData as $index => $baris) {
            $kunci = $this->normalkanNama($baris['name'] ?? null);

            if ($kunci !== '') {
                $perNama[$kunci][] = $index;
            }
        }

        foreach ($perNama as $daftar) {
            if (count($daftar) < 2) {
                continue;
            }

            foreach ($daftar as $posisi => $index) {
                if (isset($pasti[$index])) {
                    continue;
                }

                $baris = $validData[$index];

                foreach (array_slice($daftar, 0, $posisi) as $indexLain) {
                    $lain = $validData[$indexLain];
                    $calon = [
                        'label' => 'baris '.($lain['_row_number'] ?? '?'),
                        'sumber' => 'Di dalam file',
                        'asal' => $lain['asal_daerah'] ?? null,
                        'angkatan' => $lain['angkatan'] ?? null,
                        'alamat' => $lain['ktp_address'] ?? null,
                        'lahir' => $lain['birth_date'] ?? null,
                    ];

                    $nilai = $this->bandingkanIdentitas($baris, $calon);

                    if ($nilai['jenis'] === 'pasti') {
                        $pasti[$index] = $this->entriPasti($baris, $calon, $nilai['keterangan']);
                        unset($kemungkinan[$index]);

                        break;
                    }

                    if ($nilai['jenis'] === 'peringatan') {
                        $kemungkinan[$index] ??= $this->entriKemungkinan($baris, $calon, $nilai);
                    }
                }
            }
        }

        ksort($pasti);
        ksort($kemungkinan);

        $hasil['pasti'] = array_values($pasti);
        $hasil['kemungkinan'] = array_values($kemungkinan);
        $hasil['dilewati'] = array_map(
            fn (array $entri) => $entri['row'],
            $hasil['pasti']
        );

        return $hasil;
    }

    /**
     * Bandingkan identitas satu baris dengan kandidat (anak/akun/baris lain).
     *
     * Urutan pemeriksaan: asal daerah & angkatan (pembeda utama) → tanggal
     * lahir → kelengkapan alamat KTP → kesamaan alamat.
     *
     * @param  array<string, mixed>  $baris
     * @param  array<string, mixed>  $calon
     * @return array{jenis: string, keterangan: string}
     */
    protected function bandingkanIdentitas(array $baris, array $calon): array
    {
        $label = (string) ($calon['label'] ?? 'data lama');
        $sumber = (string) ($calon['sumber'] ?? 'Data');

        $asalFile = $this->normalkanNama($baris['asal_daerah'] ?? null);
        $asalCalon = $this->normalkanNama($calon['asal'] ?? null);
        $angkatanFile = $this->normalkanNama($baris['angkatan'] ?? null);
        $angkatanCalon = $this->normalkanNama($calon['angkatan'] ?? null);
        $alamatFile = $this->normalkanNama($baris['ktp_address'] ?? null);
        $alamatCalon = $this->normalkanNama($calon['alamat'] ?? null);
        $lahirFile = (string) ($this->teksOpsional($baris['birth_date'] ?? null) ?? '');
        $lahirCalon = (string) ($this->teksOpsional($calon['lahir'] ?? null) ?? '');

        $beda = [];

        if ($asalFile !== '' && $asalCalon !== '' && $asalFile !== $asalCalon) {
            $beda[] = 'asal daerah';
        }

        if ($angkatanFile !== '' && $angkatanCalon !== '' && $angkatanFile !== $angkatanCalon) {
            $beda[] = 'angkatan';
        }

        if ($lahirFile !== '' && $lahirCalon !== '' && $lahirFile !== $lahirCalon) {
            $beda[] = 'tanggal lahir';
        }

        if ($alamatFile !== '' && $alamatCalon !== '' && $alamatFile !== $alamatCalon) {
            $beda[] = 'alamat KTP';
        }

        if ($beda !== []) {
            return [
                'jenis' => 'informasi',
                'keterangan' => 'Nama sama dengan '.$label.' ('.$sumber.'), tetapi '
                    .implode(', ', $beda).' berbeda; dianggap orang lain dan tetap diimpor.',
            ];
        }

        // Identitas hanya bisa dipastikan bila alamat KTP tersedia di kedua
        // sisi (file dan data lama) dan isinya sama. Tanpa alamat, nama + asal
        // + angkatan saja tidak cukup untuk menyebutnya ganda.
        if ($alamatFile === '' || $alamatCalon === '') {
            return [
                'jenis' => 'peringatan',
                'keterangan' => 'Nama sama dengan '.$label.' ('.$sumber.'), tetapi alamat KTP kosong di '
                    .'salah satu sisi sehingga belum bisa dipastikan ganda. Baris tetap diimpor; periksa manual.',
            ];
        }

        return [
            'jenis' => 'pasti',
            'keterangan' => 'Nama dan alamat KTP sama dengan '.$label.' ('.$sumber.'), asal daerah/angkatan '
                .'tidak bertentangan, sehingga kemungkinan data ganda.',
        ];
    }

    /**
     * Entri laporan duplikat pasti (baris akan dilewati).
     *
     * @param  array<string, mixed>  $baris
     * @param  array<string, mixed>  $calon
     * @return array<string, mixed>
     */
    protected function entriPasti(array $baris, array $calon, string $keterangan): array
    {
        return [
            'row' => $baris['_row_number'] ?? null,
            'blok' => $baris['_blok'] ?? null,
            'nama' => (string) ($baris['name'] ?? ''),
            'alamat' => $this->teksOpsional($baris['ktp_address'] ?? null),
            'sumber' => (string) ($calon['sumber'] ?? 'Data lama'),
            'anak_id' => $calon['id'] ?? null,
            'keterangan' => $keterangan,
        ];
    }

    /**
     * Entri laporan nama sama yang belum bisa dipastikan ganda.
     *
     * @param  array<string, mixed>  $baris
     * @param  array<string, mixed>  $calon
     * @param  array{jenis: string, keterangan: string}  $nilai
     * @return array<string, mixed>
     */
    protected function entriKemungkinan(array $baris, array $calon, array $nilai): array
    {
        return [
            'row' => $baris['_row_number'] ?? null,
            'blok' => $baris['_blok'] ?? null,
            'nama' => (string) ($baris['name'] ?? ''),
            'alamat' => $this->teksOpsional($baris['ktp_address'] ?? null),
            'jenis' => $nilai['jenis'] === 'peringatan' ? 'Peringatan' : 'Informasi',
            'sumber' => (string) ($calon['sumber'] ?? 'Data lama'),
            'anak_id' => $calon['id'] ?? null,
            'keterangan' => $nilai['keterangan'],
        ];
    }

    /**
     * Kumpulan anak yang namanya cocok (case-insensitive) dengan daftar nama.
     *
     * Satu query untuk seluruh file, dikembalikan sebagai koleksi yang di-key
     * dengan nama ternormalisasi. Kegagalan koneksi `semarak` tidak
     * menggagalkan impor: koleksi kosong + log warning.
     *
     * @param  array<int, string>  $namaTernormalisasi
     * @return Collection<string, Collection<int, Anak>>
     */
    protected function kandidatAnak(array $namaTernormalisasi)
    {
        $namaTernormalisasi = array_values(array_unique($namaTernormalisasi));

        if ($namaTernormalisasi === []) {
            return collect();
        }

        try {
            $koleksi = collect();

            // Dipecah per 500 nama: batas parameter SQLite (999) bisa terlampaui
            // bila file berisi ribuan nama (config max_rows = 2000).
            foreach (array_chunk($namaTernormalisasi, 500) as $potongan) {
                $koleksi = $koleksi->merge(
                    Anak::query()
                        ->whereIn(DB::raw('LOWER(nama)'), $potongan)
                        ->get(['id', 'nama', 'asal_daerah', 'angkatan', 'status'])
                );
            }

            return $koleksi->groupBy(fn (Anak $anak) => $this->normalkanNama($anak->nama));
        } catch (\Throwable $e) {
            Log::warning('import.binaan.taut_gagal', [
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Kandidat anak untuk penautan, dilengkapi profil akun klien yang sudah
     * tertaut (alamat KTP & tanggal lahir) sebagai bahan pembanding identitas.
     *
     * @param  array<int, string>  $namaTernormalisasi
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    protected function kandidatTautan(array $namaTernormalisasi)
    {
        $anak = $this->kandidatAnak($namaTernormalisasi);

        if ($anak->isEmpty()) {
            return collect();
        }

        $profil = $this->profilAkunAnak($anak->collapse()->pluck('id')->all());

        return $anak->map(function (Collection $grup) use ($profil) {
            return $grup->map(function (Anak $baris) use ($profil) {
                $akun = $profil[(string) $baris->id] ?? null;

                return [
                    'id' => (string) $baris->id,
                    'akun_id' => $akun['akun_id'] ?? null,
                    'label' => (string) $baris->id,
                    'nama' => $baris->nama,
                    'asal' => $baris->asal_daerah,
                    'angkatan' => $baris->angkatan,
                    'status' => $baris->status,
                    'alamat' => $akun['alamat'] ?? '',
                    'lahir' => $akun['lahir'] ?? '',
                    'sumber' => 'Data anak',
                ];
            })->values();
        });
    }

    /**
     * Profil akun klien yang tertaut ke anak: alamat KTP & tanggal lahir,
     * di-key dengan id anak. Gagal baca (kolom belum ada/koneksi mati) →
     * daftar kosong agar impor tetap berjalan.
     *
     * @param  array<int, string>  $idAnak
     * @return array<string, array{akun_id: int, alamat: string, lahir: string}>
     */
    protected function profilAkunAnak(array $idAnak): array
    {
        if ($idAnak === [] || ! SchemaGuard::usersPunyaTautanAnak()) {
            return [];
        }

        try {
            $hasil = [];

            foreach (array_chunk($idAnak, 500) as $potongan) {
                $akun = User::query()
                    ->whereIn('anak_id', $potongan)
                    ->with('userProfile')
                    ->get(['id', 'anak_id']);

                foreach ($akun as $user) {
                    $profil = $user->userProfile;

                    $hasil[(string) $user->anak_id] = [
                        'akun_id' => (int) $user->getKey(),
                        'alamat' => (string) ($profil?->ktp_address ?? ''),
                        'lahir' => $profil?->birth_date?->format('Y-m-d') ?? '',
                    ];
                }
            }

            return $hasil;
        } catch (\Throwable $e) {
            Log::warning('import.binaan.profil_gagal', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Kandidat pembanding identitas untuk analisis duplikat.
     *
     * Sumber:
     * - `semarak.anak` (identitas dari kolom anak + profil akun yang tertaut);
     * - akun klien bernama sama yang BELUM tertaut ke anak (mis. hasil import
     *   lama), agar data yang sudah pernah masuk tidak terimpor lagi.
     *
     * @param  array<int, string>  $namaTernormalisasi
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function kandidatIdentitas(array $namaTernormalisasi): array
    {
        $namaTernormalisasi = array_values(array_unique(array_filter($namaTernormalisasi)));

        if ($namaTernormalisasi === []) {
            return [];
        }

        try {
            $hasil = [];
            $anak = $this->kandidatAnak($namaTernormalisasi);
            $profil = $this->profilAkunAnak(
                $anak->isEmpty() ? [] : $anak->collapse()->pluck('id')->all()
            );

            foreach ($anak as $kunci => $grup) {
                foreach ($grup as $baris) {
                    $akun = $profil[(string) $baris->id] ?? null;

                    $hasil[$kunci][] = [
                        'id' => (string) $baris->id,
                        'akun_id' => $akun['akun_id'] ?? null,
                        'label' => (string) $baris->id,
                        'nama' => $baris->nama,
                        'asal' => $baris->asal_daerah,
                        'angkatan' => $baris->angkatan,
                        'alamat' => $akun['alamat'] ?? '',
                        'lahir' => $akun['lahir'] ?? '',
                        'sumber' => 'Data anak',
                    ];
                }
            }

            $punyaTautan = SchemaGuard::usersPunyaTautanAnak();

            foreach (array_chunk($namaTernormalisasi, 500) as $potongan) {
                $akun = User::query()
                    ->where('user_type', User::ROLE_CLIENT)
                    ->whereIn(DB::raw('LOWER(name)'), $potongan)
                    ->with('userProfile')
                    ->get(['id', 'name', 'anak_id']);

                foreach ($akun as $user) {
                    // Anak yang sudah tertaut sudah diwakili kandidat di atas.
                    if ($punyaTautan && filled($user->anak_id)) {
                        continue;
                    }

                    $kunci = $this->normalkanNama($user->name);

                    $hasil[$kunci][] = [
                        'id' => null,
                        'akun_id' => (int) $user->getKey(),
                        'label' => 'akun #'.$user->getKey(),
                        'nama' => $user->name,
                        'asal' => null,
                        'angkatan' => null,
                        'alamat' => (string) ($user->userProfile?->ktp_address ?? ''),
                        'lahir' => $user->userProfile?->birth_date?->format('Y-m-d') ?? '',
                        'sumber' => 'Akun',
                    ];
                }
            }

            return $hasil;
        } catch (\Throwable $e) {
            Log::warning('import.binaan.duplikat_gagal', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Normalisasi untuk pembandingan: huruf kecil dan spasi dirapatkan.
     */
    protected function normalkanNama(?string $teks): string
    {
        $teks = Str::lower(trim((string) $teks));

        return $teks === '' ? '' : (string) preg_replace('/\s+/', ' ', $teks);
    }

    /**
     * Teks opsional dari file: kosong → null (bukan string kosong).
     */
    protected function teksOpsional(mixed $nilai): ?string
    {
        $teks = trim((string) $nilai);

        return $teks === '' ? null : $teks;
    }
}
