<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\MasterCity;
use App\Models\MasterEducationDegree;
use App\Models\MasterTraining;
use App\Models\User;
use App\Modules\Absensi\Models\Anak;
use App\Support\Import\SpesifikasiKolomImport;
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
     * @return array<string, mixed>
     */
    public function parseAndValidate($filePath, $extension, string $mode = self::MODE_KLIEN, bool $tautkanAnak = false)
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

        // FASE 3 (revisi review): dua laporan tambahan untuk langkah Preview.
        // Keduanya INFORMASI, bukan penolakan: baris tetap masuk `valid` dan
        // tetap diimpor. Keputusan penautan ada di tangan operator (checkbox),
        // sedangkan laporan duplikat hanya meminta operator memeriksa.
        $validasi['tautan'] = $this->petaTautanAnak($validasi['valid'], $validasi['tautkan_anak']);
        $validasi['kemungkinan_duplikat'] = $this->deteksiKemungkinanDuplikat($validasi['valid']);

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
     * Baca tabel pertama yang punya baris header pada dokumen Word.
     *
     * Template baru memuat tabel "Keterangan Kolom" sebelum tabel DATA, maka
     * pencarian tidak boleh berhenti di tabel pertama saja. Tabel keterangan
     * tidak pernah dianggap tabel data karena header-nya tidak memuat kolom
     * "Nama" (hanya "Nama Kolom").
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseWord($filePath)
    {
        $phpWord = WordIOFactory::load($filePath);

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (! $element instanceof Table) {
                    continue;
                }

                $matriks = $this->matriksTabel($element);

                if ($this->cariBarisHeader($matriks) !== null) {
                    return $this->petakanBaris($matriks);
                }
            }
        }

        return [];
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
     * Kolom nomor urut ("No") berisi rumus pada 200 baris pra-format, sehingga
     * TIDAK boleh dipakai sebagai penanda "baris berisi data".
     *
     * @param  array<string, mixed>  $mapped
     */
    protected function barisBerisiData(array $mapped): bool
    {
        foreach (SpesifikasiKolomImport::kunciData() as $kunci) {
            $nilai = trim((string) ($mapped[$kunci] ?? ''));

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
                        'name' => $nama,
                        'col' => $err['col'],
                        'val' => $err['val'],
                        'msg' => $err['msg'],
                    ];
                }
            } else {
                $parsedData['_row_number'] = $rowNum;
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
     * @return array{mode: string, anak_baru: int, ditautkan: int, akun_dibuat: int, gagal: int, gagal_detail: array<int, array<string, mixed>>}
     */
    public function importData(array $validData, array $uploadedPhotos = [], ?int $createdBy = null, ?string $initialPassword = null, string $mode = self::MODE_KLIEN, bool $tautkanAnak = false): array
    {
        if ($mode === self::MODE_BINAAN) {
            return $this->importBinaan($validData, $uploadedPhotos, $createdBy, $tautkanAnak);
        }

        return $this->importKlien($validData, $uploadedPhotos, $createdBy, $initialPassword);
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
     * @return array{mode: string, anak_baru: int, ditautkan: int, akun_dibuat: int, gagal: int, gagal_detail: array<int, array<string, mixed>>}
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
        $kandidat = $this->kandidatAnak(array_keys($kemunculan));
        $sudahBerakun = [];

        if ($kandidat->isNotEmpty()) {
            // collapse(): `$kandidat` adalah koleksi hasil groupBy, jadi id anak
            // diambil setelah lapisan grup dirata-kan. Dipecah per 500 id agar
            // batas parameter query tidak terlampaui pada file besar.
            $idKandidat = $kandidat->collapse()->pluck('id')->all();

            foreach (array_chunk($idKandidat, 500) as $potongan) {
                foreach (User::query()->whereIn('anak_id', $potongan)->pluck('anak_id') as $anakId) {
                    $sudahBerakun[(string) $anakId] = true;
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

            if (($kemunculan[$kunci] ?? 0) > 1) {
                $alasan = 'Nama ini muncul lebih dari sekali di file';
            } elseif ($calon->isEmpty()) {
                $alasan = 'Tidak ada data anak dengan nama ini';
            } elseif ($calon->count() > 1) {
                $alasan = 'Nama kembar: '.$calon->count().' anak memakai nama ini';
            } else {
                /** @var Anak $anak */
                $anak = $calon->first();

                if (isset($sudahBerakun[(string) $anak->id])) {
                    $alasan = 'Anak '.$anak->id.' sudah punya akun klien';
                } else {
                    $alasan = $this->konflikIdentitas($baris, $anak);
                }
            }

            if ($alasan !== null) {
                $hasil['tidak'][] = [
                    'row' => $row,
                    'nama' => $nama,
                    'email' => $email,
                    'alasan' => $alasan,
                ];
                $hasil['rencana'][] = [
                    'row' => $row,
                    'nama' => $nama,
                    'email' => $email,
                    'aksi' => 'anak_baru',
                    'anak_id' => null,
                    'keterangan' => 'Anak baru + akun Draft ('.$alasan.')',
                ];

                continue;
            }

            /** @var Anak $anak */
            $anak = $calon->first();

            $hasil['akan'][] = [
                'row' => $row,
                'nama' => $nama,
                'email' => $email,
                'anak_id' => (string) $anak->id,
                'asal_daerah' => $anak->asal_daerah,
                'angkatan' => $anak->angkatan,
                'status' => $anak->status,
            ];
            $hasil['peta'][(string) $row] = (string) $anak->id;
            $hasil['rencana'][] = [
                'row' => $row,
                'nama' => $nama,
                'email' => $email,
                'aksi' => 'tautkan',
                'anak_id' => (string) $anak->id,
                'keterangan' => 'Tautkan ke '.$anak->id.' (akun Draft, tanpa anak baru)',
            ];
        }

        return $hasil;
    }

    /**
     * Asal daerah/angkatan di file bertentangan dengan data anak?
     *
     * @param  array<string, mixed>  $baris
     */
    protected function konflikIdentitas(array $baris, Anak $anak): ?string
    {
        $asalFile = $this->teksOpsional($baris['asal_daerah'] ?? null);
        $angkatanFile = $this->teksOpsional($baris['angkatan'] ?? null);

        if ($asalFile !== null && $this->normalkanNama($asalFile) !== $this->normalkanNama($anak->asal_daerah)) {
            return 'Asal daerah berbeda dengan data anak '.$anak->id;
        }

        if ($angkatanFile !== null && $this->normalkanNama($angkatanFile) !== $this->normalkanNama($anak->angkatan)) {
            return 'Angkatan berbeda dengan data anak '.$anak->id;
        }

        return null;
    }

    /**
     * Laporkan baris yang MUNGKIN duplikat: nama + asal daerah + angkatan.
     *
     * Sifatnya informasi saja — baris tidak ditolak (keputusan ada di operator
     * pada langkah Preview).
     *
     * Tiga sumber perbandingan:
     * 1. `Di dalam file` — dua baris atau lebih dengan nama, asal daerah, dan
     *    angkatan yang sama.
     * 2. `Data anak` — nama sama dengan `semarak.anak`; asal daerah/angkatan
     *    dipakai sebagai pembeda bila kolomnya diisi di file.
     * 3. `Akun yang sudah ada` — nama sama dengan akun klien lain (beda email),
     *    tanda data sudah pernah diimpor.
     *
     * @param  array<int, array<string, mixed>>  $validData
     * @return array<int, array<string, mixed>>
     */
    public function deteksiKemungkinanDuplikat(array $validData): array
    {
        $hasil = [];

        if ($validData === []) {
            return $hasil;
        }

        // 1. Duplikat di dalam file (nama + asal daerah + angkatan).
        $kelompok = [];

        foreach ($validData as $baris) {
            $kunci = $this->kunciDuplikat($baris);

            if ($kunci !== null) {
                $kelompok[$kunci][] = $baris;
            }
        }

        foreach ($kelompok as $barisSerupa) {
            if (count($barisSerupa) < 2) {
                continue;
            }

            $nomor = array_map(fn (array $b) => $b['_row_number'] ?? null, $barisSerupa);

            foreach ($barisSerupa as $baris) {
                $lain = array_values(array_filter(
                    $nomor,
                    fn ($n) => $n !== ($baris['_row_number'] ?? null)
                ));

                $hasil[] = [
                    'row' => $baris['_row_number'] ?? null,
                    'nama' => $baris['name'] ?? null,
                    'email' => $baris['email'] ?? null,
                    'jenis' => 'Di dalam file',
                    'keyakinan' => 'Tinggi',
                    'keterangan' => 'Nama, asal daerah, dan angkatan sama dengan baris '
                        .implode(', ', array_map(fn ($n) => (string) $n, $lain)).'.',
                ];
            }
        }

        return array_merge($hasil, $this->duplikatTerhadapDataLama($validData));
    }

    /**
     * Bagian (2) & (3) dari deteksi duplikat: bandingkan nama dengan data anak
     * dan akun yang sudah ada. Asal daerah/angkatan dari file dipakai sebagai
     * pembeda: bila file menyebut asal/angkatan yang BERBEDA, baris dianggap
     * orang lain dan tidak dilaporkan.
     *
     * @param  array<int, array<string, mixed>>  $validData
     * @return array<int, array<string, mixed>>
     */
    protected function duplikatTerhadapDataLama(array $validData): array
    {
        $hasil = [];

        $kunciNama = array_values(array_unique(array_filter(array_map(
            fn (array $baris) => $this->normalkanNama($baris['name'] ?? null),
            $validData
        ))));

        if ($kunciNama === []) {
            return $hasil;
        }

        $kandidat = $this->kandidatAnak($kunciNama);

        // Dipecah per 500 nama agar batas parameter query tidak terlampaui.
        $akunSerupa = collect();

        foreach (array_chunk($kunciNama, 500) as $potongan) {
            $akunSerupa = $akunSerupa->merge(
                User::query()
                    ->whereIn(DB::raw('LOWER(name)'), $potongan)
                    ->get(['name', 'email'])
            );
        }

        $akunSerupa = $akunSerupa->groupBy(fn (User $user) => $this->normalkanNama($user->name));

        foreach ($validData as $baris) {
            $nama = trim((string) ($baris['name'] ?? ''));
            $kunci = $this->normalkanNama($nama);

            if ($kunci === '') {
                continue;
            }

            $asalFile = $this->teksOpsional($baris['asal_daerah'] ?? null);
            $angkatanFile = $this->teksOpsional($baris['angkatan'] ?? null);

            foreach ($kandidat->get($kunci, collect()) as $anak) {
                $cocokAsal = $asalFile === null
                    ? null
                    : $this->normalkanNama($asalFile) === $this->normalkanNama($anak->asal_daerah);
                $cocokAngkatan = $angkatanFile === null
                    ? null
                    : $this->normalkanNama($angkatanFile) === $this->normalkanNama($anak->angkatan);

                if ($cocokAsal === false || $cocokAngkatan === false) {
                    continue;
                }

                $pasti = $cocokAsal === true && $cocokAngkatan === true;

                $hasil[] = [
                    'row' => $baris['_row_number'] ?? null,
                    'nama' => $nama,
                    'email' => $baris['email'] ?? null,
                    'jenis' => 'Data anak',
                    'keyakinan' => $pasti ? 'Tinggi' : 'Sedang',
                    'keterangan' => 'Nama sama dengan anak '.$anak->id
                        .' ('.$anak->asal_daerah.' · '.$anak->angkatan.', status '.$anak->status.').'
                        .($pasti
                            ? ' Asal daerah dan angkatan juga sama.'
                            : ' Asal daerah/angkatan di file belum diisi.'),
                ];
            }

            foreach ($akunSerupa->get($kunci, collect()) as $akun) {
                if (Str::lower((string) $akun->email) === Str::lower((string) ($baris['email'] ?? ''))) {
                    continue;
                }

                $hasil[] = [
                    'row' => $baris['_row_number'] ?? null,
                    'nama' => $nama,
                    'email' => $baris['email'] ?? null,
                    'jenis' => 'Akun yang sudah ada',
                    'keyakinan' => 'Sedang',
                    'keterangan' => 'Sudah ada akun dengan nama sama: '.$akun->email.'.',
                ];
            }
        }

        return $hasil;
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
     * Kunci pembanding duplikat: nama + asal daerah + angkatan.
     *
     * @param  array<string, mixed>  $baris
     */
    protected function kunciDuplikat(array $baris): ?string
    {
        $nama = $this->normalkanNama($baris['name'] ?? null);

        if ($nama === '') {
            return null;
        }

        return $nama
            .'|'.$this->normalkanNama($baris['asal_daerah'] ?? null)
            .'|'.$this->normalkanNama($baris['angkatan'] ?? null);
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
