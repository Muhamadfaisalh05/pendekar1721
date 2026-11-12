<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\MasterCity;
use App\Models\MasterEducationDegree;
use App\Models\MasterTraining;
use App\Models\User;
use App\Modules\Absensi\Models\Anak;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;

class ImportService
{
    /**
     * FASE 3 — mode import.
     *
     * - `MODE_BINAAN`: akun dibuat `Draft` TANPA kata sandi. Tidak bisa login
     *   sampai admin menjalankan aksi "Luluskan & Aktifkan Akun Klien".
     * - `MODE_KLIEN`: akun langsung `Published` (perilaku lama untuk klien umum).
     */
    public const MODE_BINAAN = 'binaan';

    public const MODE_KLIEN = 'klien';

    /**
     * Pilihan mode untuk form Import Data.
     *
     * @return array<string, string>
     */
    public static function pilihanMode(): array
    {
        return [
            self::MODE_BINAAN => 'Remaja Binaan (akun Draft, aktif saat lulus)',
            self::MODE_KLIEN => 'Klien Umum (akun langsung aktif)',
        ];
    }

    protected $cities;

    protected $educations;

    protected $trainings;

    public function __construct()
    {
        $this->cities = MasterCity::pluck('id', 'title')->mapWithKeys(function ($item, $key) {
            return [strtolower(trim($key)) => $item];
        })->toArray();

        $this->educations = MasterEducationDegree::pluck('id', 'title')->mapWithKeys(function ($item, $key) {
            return [strtolower(trim($key)) => $item];
        })->toArray();

        $this->trainings = MasterTraining::pluck('id', 'title')->mapWithKeys(function ($item, $key) {
            return [strtolower(trim($key)) => $item];
        })->toArray();
    }

    public function parseAndValidate($filePath, $extension)
    {
        $rows = [];
        if (in_array($extension, ['xlsx', 'xls', 'csv'])) {
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

        $validasi = $this->validateRows($rows);

        // FASE 3 (revisi review): dua laporan tambahan untuk langkah Preview.
        // Keduanya INFORMASI, bukan penolakan: baris tetap masuk `valid` dan
        // tetap diimpor. Keputusan penautan ada di tangan operator (checkbox),
        // sedangkan laporan duplikat hanya meminta operator memeriksa.
        $validasi['tautan'] = $this->petaTautanAnak($validasi['valid']);
        $validasi['kemungkinan_duplikat'] = $this->deteksiKemungkinanDuplikat($validasi['valid']);

        return $validasi;
    }

    protected function parseExcel($filePath)
    {
        // setReadDataOnly(true): jangan memuat style, objek, atau makro dari
        // file yang diunggah pengguna; hanya nilai sel yang dibutuhkan.
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = [];
        $header = [];

        foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getCalculatedValue();
            }

            if ($rowIndex === 1) {
                // Read header and sanitize
                $header = array_map(function ($h) {
                    return strtolower(trim(str_replace('*', '', $h)));
                }, $rowData);
            } else {
                // Map to header
                $mapped = [];
                foreach ($header as $index => $colName) {
                    if ($colName) {
                        $mapped[$colName] = $rowData[$index] ?? null;
                    }
                }
                if (count(array_filter($mapped)) > 0) { // skip empty rows
                    $mapped['_row_number'] = $rowIndex;
                    $rows[] = $mapped;
                }
            }
        }

        return $rows;
    }

    protected function parseWord($filePath)
    {
        $phpWord = WordIOFactory::load($filePath);
        $rows = [];
        $header = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof Table) {
                    $rowIndex = 1;
                    foreach ($element->getRows() as $row) {
                        $rowData = [];
                        foreach ($row->getCells() as $cell) {
                            $text = '';
                            foreach ($cell->getElements() as $cellElement) {
                                if ($cellElement instanceof TextRun) {
                                    foreach ($cellElement->getElements() as $textElement) {
                                        if ($textElement instanceof Text) {
                                            $text .= $textElement->getText();
                                        }
                                    }
                                }
                            }
                            $rowData[] = trim($text);
                        }

                        if ($rowIndex === 1) {
                            $header = array_map(function ($h) {
                                return strtolower(trim(str_replace('*', '', $h)));
                            }, $rowData);
                        } else {
                            $mapped = [];
                            foreach ($header as $index => $colName) {
                                if ($colName) {
                                    $mapped[$colName] = $rowData[$index] ?? null;
                                }
                            }
                            if (count(array_filter($mapped)) > 0) {
                                $mapped['_row_number'] = $rowIndex;
                                $rows[] = $mapped;
                            }
                        }
                        $rowIndex++;
                    }
                    break; // Only read the first table found
                }
            }
        }

        return $rows;
    }

    protected function validateRows(array $rows)
    {
        $results = [
            'valid' => [],
            'errors' => [],
            'duplicates' => [],
            'total' => count($rows),
        ];

        // Fetch all existing emails to fast-check duplicates
        $existingEmails = User::pluck('email')->map(fn ($e) => strtolower($e))->toArray();

        foreach ($rows as $row) {
            $rowNum = $row['_row_number'];
            $errors = [];
            $parsedData = [];

            // 1. Nama (Required)
            $nama = $row['nama'] ?? null;
            if (empty($nama)) {
                $errors[] = ['col' => 'Nama', 'val' => '', 'msg' => 'Nama wajib diisi'];
            } else {
                $parsedData['name'] = $nama;
            }

            // 2. Email (Required & Unique)
            $email = $row['email'] ?? null;
            if (empty($email)) {
                $errors[] = ['col' => 'Email', 'val' => '', 'msg' => 'Email wajib diisi'];
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['col' => 'Email', 'val' => $email, 'msg' => 'Format email tidak valid'];
            } else {
                if (in_array(strtolower($email), $existingEmails)) {
                    $results['duplicates'][] = [
                        'row' => $rowNum,
                        'name' => $nama,
                        'email' => $email,
                    ];

                    continue; // Skip further validation for duplicates
                }
                $parsedData['email'] = $email;
            }

            // 3. Jenis Kelamin (L/P)
            $jk = strtoupper(trim($row['jenis kelamin'] ?? ''));
            if (! empty($jk) && ! in_array($jk, ['L', 'P'])) {
                $errors[] = ['col' => 'Jenis Kelamin', 'val' => $jk, 'msg' => 'Harus L atau P'];
            }
            $parsedData['gender'] = in_array($jk, ['L', 'P']) ? $jk : null;

            // 4. Tempat Lahir
            $parsedData['birth_place'] = $row['tempat lahir'] ?? null;

            // 5. Tanggal Lahir
            $tgl = $row['tanggal lahir'] ?? null;
            if (! empty($tgl)) {
                if (is_numeric($tgl)) {
                    // Excel serialized date
                    $unixDate = ($tgl - 25569) * 86400;
                    $parsedData['birth_date'] = gmdate('Y-m-d', $unixDate);
                } else {
                    $parsedData['birth_date'] = date('Y-m-d', strtotime($tgl));
                }
            }

            // 6. Alamat KTP
            $parsedData['ktp_address'] = $row['alamat ktp'] ?? null;

            // 7. Kota KTP
            $kotaKtp = trim($row['kota ktp'] ?? '');
            if (! empty($kotaKtp)) {
                $kotaKey = strtolower($kotaKtp);
                if (isset($this->cities[$kotaKey])) {
                    $parsedData['ktp_city_id'] = $this->cities[$kotaKey];
                } else {
                    $errors[] = ['col' => 'Kota KTP', 'val' => $kotaKtp, 'msg' => 'Data kota tidak ditemukan di sistem'];
                }
            }

            // 8. Pendidikan
            $pendidikan = trim($row['pendidikan'] ?? '');
            if (! empty($pendidikan)) {
                $pendidikanKey = strtolower($pendidikan);
                if (isset($this->educations[$pendidikanKey])) {
                    $parsedData['education_degree_id'] = $this->educations[$pendidikanKey];
                } else {
                    $errors[] = ['col' => 'Pendidikan', 'val' => $pendidikan, 'msg' => 'Data pendidikan tidak ditemukan di sistem'];
                }
            }

            // 9. Pelatihan (Multiple)
            $parsedData['trainings'] = [];
            $pelatihan = trim($row['pelatihan'] ?? '');
            if (! empty($pelatihan)) {
                $items = array_map('trim', explode(',', $pelatihan));
                foreach ($items as $item) {
                    $itemKey = strtolower($item);
                    if (isset($this->trainings[$itemKey])) {
                        $parsedData['trainings'][] = $this->trainings[$itemKey];
                    } else {
                        $errors[] = ['col' => 'Pelatihan', 'val' => $item, 'msg' => 'Pelatihan tidak ditemukan di sistem'];
                    }
                }
            }

            // 10. Penempatan Wilayah (Multiple)
            $parsedData['work_locations'] = [];
            $wilayah = trim($row['penempatan wilayah'] ?? '');
            if (! empty($wilayah)) {
                $items = array_map('trim', explode(',', $wilayah));
                foreach ($items as $item) {
                    $itemKey = strtolower($item);
                    if (isset($this->cities[$itemKey])) {
                        $parsedData['work_locations'][] = $this->cities[$itemKey];
                    } else {
                        $errors[] = ['col' => 'Penempatan Wilayah', 'val' => $item, 'msg' => 'Wilayah tidak ditemukan di sistem'];
                    }
                }
            }

            // 11. Status Pekerjaan
            $statusKerja = trim(strtolower($row['status pekerjaan'] ?? ''));
            $parsedData['is_current_job'] = ($statusKerja === 'sedang bekerja' || $statusKerja === '1');

            // 12. Deskripsi
            $parsedData['description'] = $row['deskripsi'] ?? null;

            // 13. Status Profil
            $statusProfil = trim(strtolower($row['status profil'] ?? ''));
            $parsedData['hire_status'] = ($statusProfil === 'tersedia' || $statusProfil === '1') ? '1' : '0';

            // 14. Nama File Foto
            $parsedData['photo_filename'] = trim($row['nama file foto'] ?? '');

            // 15. Asal Daerah & Angkatan (OPSIONAL).
            //
            // Dua kolom ini tidak disimpan ke tabel `users`; hanya dipakai
            // untuk (a) deteksi "kemungkinan duplikat" terhadap data anak dan
            // (b) keterangan pada langkah Preview. Bila kolomnya tidak ada di
            // file, nilainya null dan deteksi jatuh ke perbandingan nama saja.
            $parsedData['asal_daerah'] = $this->teksOpsional($row['asal daerah'] ?? null);
            $parsedData['angkatan'] = $this->teksOpsional($row['angkatan'] ?? null);

            // Compile errors or valid data
            if (count($errors) > 0) {
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
     * Simpan data hasil preview menjadi user + profil.
     *
     * @param  array<int, array<string, mixed>>  $validData
     * @param  array<int, string>  $uploadedPhotos
     * @param  int|null  $createdBy  Pemilik data (id klien/admin yang mengimpor).
     * @param  string|null  $initialPassword  Kata sandi awal opsional (sudah diverifikasi
     *                                        terhadap IMPORT_PASSWORD_HASH). Bila null,
     *                                        peserta mendapat password acak.
     * @param  string  $mode  Mode import: `binaan` (Draft, tanpa kata sandi) atau
     *                        `klien` (Published + kata sandi). Default `klien` agar
     *                        pemanggil lama tidak berubah perilaku; form Import Data
     *                        memakai `binaan` sebagai pilihan awal.
     * @param  bool  $tautkanAnak  Hanya berlaku untuk mode `binaan`. Bila true,
     *                             akun ditautkan ke `semarak.anak` memakai aturan
     *                             yang sama dengan `petaTautanAnak()` (nama unik,
     *                             belum berakun). Default false: akun dibuat
     *                             tanpa tautan, operator harus memilih sendiri
     *                             lewat checkbox di form Import Data.
     */
    public function importData(array $validData, array $uploadedPhotos = [], ?int $createdBy = null, ?string $initialPassword = null, string $mode = self::MODE_KLIEN, bool $tautkanAnak = false)
    {
        $modeBinaan = $mode === self::MODE_BINAAN;

        // Mode binaan: tidak ada kata sandi sama sekali (kolom NULL) sehingga
        // akun tidak bisa login sampai proses kelulusan. Kata sandi awal
        // operator sengaja diabaikan di mode ini.
        //
        // Mode klien: SECURITY (FASE 3) tidak ada password default yang
        // diketahui publik. Tanpa kata sandi awal, peserta tidak bisa login
        // sampai reset password.
        $plainPassword = $modeBinaan
            ? null
            : (filled($initialPassword)
                ? $initialPassword
                : Str::password((int) config('pendekar.import.password_length', 32)));

        // Penautan ke data anak hanya bila diminta operator (checkbox).
        // Peta dihitung sekali di depan dengan fungsi yang sama yang dipakai
        // langkah Preview, sehingga yang ditautkan = yang dilihat operator.
        $petaTautan = ($modeBinaan && $tautkanAnak)
            ? $this->petaTautanAnak($validData)['peta']
            : [];

        $totalTertaut = 0;

        DB::beginTransaction();
        try {
            foreach ($validData as $data) {
                $anakId = $petaTautan[Str::lower(trim((string) ($data['email'] ?? '')))] ?? null;

                // 1. Create User
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $modeBinaan ? null : Hash::make($plainPassword),
                    'user_type' => 'client',
                    'status' => $modeBinaan ? UserStatus::Draft : UserStatus::Published,
                    'created_by' => $createdBy,
                    'anak_id' => $anakId,
                ]);

                if ($anakId !== null) {
                    $totalTertaut++;
                }

                // Match Photo
                $photoPath = null;
                if (! empty($data['photo_filename']) && count($uploadedPhotos) > 0) {
                    $targetFilename = strtolower(trim($data['photo_filename']));
                    foreach ($uploadedPhotos as $uploadedPhoto) {
                        // $uploadedPhoto is usually a path like "profile_pictures/budi.jpg"
                        $uploadedBasename = strtolower(basename($uploadedPhoto));
                        if ($uploadedBasename === $targetFilename) {
                            $photoPath = $uploadedPhoto;
                            break;
                        }
                    }
                }

                // 2. Create UserProfile
                // user_id diisi otomatis oleh relasi (bukan mass assignment).
                $user->userProfile()->create([
                    'gender' => $data['gender'] ?? null,
                    'birth_place' => $data['birth_place'] ?? null,
                    'birth_date' => $data['birth_date'] ?? null,
                    'ktp_address' => $data['ktp_address'] ?? null,
                    'ktp_city_id' => $data['ktp_city_id'] ?? null,
                    'education_degree_id' => $data['education_degree_id'] ?? null,
                    'description' => $data['description'] ?? null,
                    'hire_status' => $data['hire_status'] ?? '0',
                    'profile_picture_path' => $photoPath,
                ]);

                // 3. Attach Trainings
                if (! empty($data['trainings'])) {
                    $user->userTrainings()->attach($data['trainings']);
                }

                // 4. Attach Work Locations
                if (! empty($data['work_locations'])) {
                    $user->userWorkLocations()->attach($data['work_locations']);
                }

                // 5. Create current job experience if applicable
                if (! empty($data['is_current_job']) && $data['is_current_job'] === true) {
                    $user->userExperiences()->create([
                        'experience_job_name' => 'Bekerja Saat Ini',
                        'experience_description' => 'Di-import dari sistem',
                        'is_current_job' => true,
                        'sort_order' => 1,
                    ]);
                }
            }
            DB::commit();

            // Jejak audit: siapa mengimpor berapa baris, dengan mode apa.
            Log::info('import.peserta', [
                'actor_id' => $createdBy,
                'rows' => count($validData),
                'mode' => $mode,
                'pakai_kata_sandi_awal' => filled($plainPassword),
                'tautkan_anak' => $tautkanAnak,
                'akun_tertaut_ke_anak' => $totalTertaut,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Import Error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Rencana penautan akun binaan ke `semarak.anak` SEBELUM impor.
     *
     * Dipakai dua kali dengan aturan identik, sehingga yang ditautkan saat
     * impor persis sama dengan yang dilihat operator di langkah Preview:
     * - `parseAndValidate()` → ditampilkan di Preview;
     * - `importData(..., tautkanAnak: true)` → nilai `peta` yang dipakai.
     *
     * Aturan keamanan data:
     * - Hanya nama yang PERSIS sama (tanpa beda kapital/spasi) yang dihitung.
     * - Nama kembar (lebih dari satu anak memakai nama itu) TIDAK PERNAH
     *   ditautkan, meski operator mencentang opsi — tidak ada dasar untuk
     *   menebak anak yang mana.
     * - Nama yang muncul lebih dari sekali di file juga tidak ditautkan,
     *   karena satu anak hanya boleh punya satu akun.
     * - Anak yang sudah punya akun klien tidak ditautkan (unique `users.anak_id`).
     * - Bila koneksi `semarak` tidak terbaca, impor tetap jalan tanpa tautan
     *   (dicatat sebagai warning): data klien tidak boleh hilang hanya karena
     *   database SEMARAK sedang bermasalah.
     *
     * @param  array<int, array<string, mixed>>  $validData
     * @return array{akan: array<int, array<string, mixed>>, tidak: array<int, array<string, mixed>>, peta: array<string, string>}
     */
    public function petaTautanAnak(array $validData): array
    {
        $hasil = ['akan' => [], 'tidak' => [], 'peta' => []];

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
                $anak = $calon->first();
                if (isset($sudahBerakun[(string) $anak->id])) {
                    $alasan = 'Anak '.$anak->id.' sudah punya akun klien';
                }
            }

            if ($alasan !== null) {
                $hasil['tidak'][] = [
                    'row' => $row,
                    'nama' => $nama,
                    'email' => $email,
                    'alasan' => $alasan,
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
            $hasil['peta'][Str::lower($email)] = (string) $anak->id;
        }

        return $hasil;
    }

    /**
     * Laporkan baris yang MUNGKIN duplikat: nama + asal daerah + angkatan.
     *
     * Sifatnya informasi saja — baris tetap dianggap valid dan tetap diimpor
     * (keputusan pemilik produk: jangan menolak diam-diam, cukup tampilkan di
     * Preview supaya operator bisa memeriksa dan memutuskan sendiri).
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
