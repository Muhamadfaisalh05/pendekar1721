<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Modules\Absensi\Models\Anak;
use App\Modules\Absensi\Services\AnakService;
use App\Support\SchemaGuard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * FASE 2 — jembatan `semarak.anak` → `users`.
 *
 * Tugasnya SATU: memastikan setiap anak binaan punya akun klien berstatus
 * `Draft` yang tertaut lewat `users.anak_id`, sehingga saat binaan lulus
 * (lihat GraduationService) akun yang sama diaktifkan — bukan membuat akun
 * baru yang menumpuk.
 *
 * Aturan:
 * - Akun binaan dibuat `Draft`, `email = NULL`, `password = NULL`.
 *   Dengan begitu akun tidak bisa login (guard di LoginController) sampai
 *   diaktifkan pada proses kelulusan.
 * - Idempoten: memanggil dua kali untuk anak yang sama tidak membuat akun
 *   kedua (dijaga `users.anak_id` yang unique).
 * - Nama kembar tidak otomatis diimpor agar tidak tertukar dengan akun
 *   klien yang sudah ada. Gunakan `$izinkanNamaGanda = true` bila operator
 *   sudah memverifikasi bahwa memang orang yang berbeda.
 */
final class ImportBinaanService
{
    /** Pesan standar bila migrasi Fase 2 belum dijalankan. */
    public const PESAN_MIGRASI_TERTUNDA = 'Migrasi database belum dijalankan: kolom tautan akun (users.anak_id) belum ada. '
        .'Jalankan `php artisan migrate` terlebih dahulu.';

    /** Anak sudah punya akun tertaut; tidak ada yang dibuat. */
    public const STATUS_SUDAH_TERPAUT = 'sudah_tertaut';

    /** Akun binaan baru dibuat dan langsung tertaut. */
    public const STATUS_DIBUAT = 'dibuat';

    /** Dilewati: sudah ada akun klien bernama sama tanpa tautan `anak_id`. */
    public const STATUS_DILEWATI_NAMA_KEMBAR = 'dilewati_nama_kembar';

    /**
     * Pastikan seorang anak punya akun klien Draft yang tertaut.
     *
     * @return array{user: User|null, status: string, dibuat: bool, pesan: string}
     */
    public function pastikanAkunBinaan(Anak $anak, ?int $createdBy = null, bool $izinkanNamaGanda = false): array
    {
        // PENGAMAN SKEMA: tanpa `users.anak_id` setiap query di bawah gagal
        // dengan error SQL. Ditolak lebih awal dengan pesan yang jelas.
        if (! SchemaGuard::usersPunyaTautanAnak()) {
            throw new InvalidArgumentException(self::PESAN_MIGRASI_TERTUNDA);
        }

        $tertaut = User::query()->where('anak_id', $anak->getKey())->first();

        if ($tertaut !== null) {
            return [
                'user' => $tertaut,
                'status' => self::STATUS_SUDAH_TERPAUT,
                'dibuat' => false,
                'pesan' => "Anak {$anak->getKey()} sudah tertaut ke akun #{$tertaut->getKey()}.",
            ];
        }

        if (! $izinkanNamaGanda && $this->adaNamaKembar($anak)) {
            return [
                'user' => null,
                'status' => self::STATUS_DILEWATI_NAMA_KEMBAR,
                'dibuat' => false,
                'pesan' => "Sudah ada akun klien bernama \"{$anak->nama}\"; verifikasi manual sebelum menautkan.",
            ];
        }

        $user = DB::transaction(function () use ($anak, $createdBy): User {
            $user = User::create([
                'name' => $anak->nama,
                'email' => null,
                'password' => null,
                'user_type' => User::ROLE_CLIENT,
                'status' => UserStatus::Draft,
                'created_by' => $createdBy,
                'anak_id' => (string) $anak->getKey(),
            ]);

            // Profil minimal supaya halaman "Ubah Profil" tidak kosong total.
            $user->userProfile()->create([
                'gender' => self::petakanGender($anak->jenis_kelamin),
            ]);

            return $user;
        });

        Log::info('import.binaan', [
            'anak_id' => $anak->getKey(),
            'user_id' => $user->getKey(),
            'created_by' => $createdBy,
        ]);

        return [
            'user' => $user,
            'status' => self::STATUS_DIBUAT,
            'dibuat' => true,
            'pesan' => "Akun binaan untuk {$anak->nama} dibuat (Draft, belum bisa login).",
        ];
    }

    /**
     * Impor banyak anak sekaligus. Satu anak gagal tidak menghentikan sisanya.
     *
     * @param  iterable<int, Anak|string>  $daftarAnak  model Anak atau ID anak
     * @return array{dibuat: int, sudah_tertaut: int, dilewati_nama_kembar: int, tidak_ada: int, gagal: int, detail: list<string>}
     */
    public function importSemua(iterable $daftarAnak, ?int $createdBy = null, bool $izinkanNamaGanda = false): array
    {
        $ringkasan = [
            'dibuat' => 0,
            'sudah_tertaut' => 0,
            'dilewati_nama_kembar' => 0,
            'tidak_ada' => 0,
            'gagal' => 0,
            'detail' => [],
        ];

        foreach ($daftarAnak as $item) {
            $anak = $item instanceof Anak ? $item : Anak::query()->find((string) $item);

            if ($anak === null) {
                $ringkasan['tidak_ada']++;
                $ringkasan['detail'][] = 'Anak tidak ditemukan: '.(string) $item;

                continue;
            }

            try {
                $hasil = $this->pastikanAkunBinaan($anak, $createdBy, $izinkanNamaGanda);
            } catch (\Throwable $e) {
                $ringkasan['gagal']++;
                $ringkasan['detail'][] = "Gagal impor {$anak->getKey()}: {$e->getMessage()}";

                Log::error('import.binaan.gagal', [
                    'anak_id' => $anak->getKey(),
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $ringkasan[$hasil['status']]++;
            $ringkasan['detail'][] = $hasil['pesan'];
        }

        Log::info('import.binaan.ringkasan', [
            'created_by' => $createdBy,
            'dibuat' => $ringkasan['dibuat'],
            'sudah_tertaut' => $ringkasan['sudah_tertaut'],
            'dilewati_nama_kembar' => $ringkasan['dilewati_nama_kembar'],
            'tidak_ada' => $ringkasan['tidak_ada'],
            'gagal' => $ringkasan['gagal'],
        ]);

        return $ringkasan;
    }

    /**
     * Pemetaan jenis kelamin SEMARAK ("Laki-laki"/"Perempuan") ke kode profil
     * aplikasi ("L"/"P"). Nilai tak dikenal menghasilkan NULL, bukan tebakan.
     */
    public static function petakanGender(?string $jenisKelamin): ?string
    {
        $nilai = Str::lower(trim((string) $jenisKelamin));

        if ($nilai === '') {
            return null;
        }

        return match (true) {
            str_starts_with($nilai, 'l'),
            str_starts_with($nilai, 'm') => 'L',
            str_starts_with($nilai, 'p'),
            str_starts_with($nilai, 'w') => 'P',
            default => null,
        };
    }

    /**
     * Sudah ada akun klien bernama sama yang belum tertaut ke anak mana pun?
     */
    private function adaNamaKembar(Anak $anak): bool
    {
        $nama = Str::lower(trim((string) $anak->nama));

        if ($nama === '') {
            return false;
        }

        return User::query()
            ->where('user_type', User::ROLE_CLIENT)
            // Bila kolom tautan belum ada, tidak ada akun yang bisa tertaut,
            // sehingga semua akun bernama sama dianggap "belum tertaut".
            ->when(
                SchemaGuard::usersPunyaTautanAnak(),
                fn ($query) => $query->whereNull('anak_id'),
            )
            ->whereRaw('LOWER(name) = ?', [$nama])
            ->exists();
    }

    /**
     * Buat satu baris `semarak.anak` baru dengan ID `AN-xxx` yang dijamin unik.
     *
     * PROTEKSI BENTROK: `AnakService::generateId()` menghitung nomor tertinggi
     * saat ini, sehingga dua import bersamaan bisa menghasilkan ID yang sama.
     * ID dicek ulang sebelum insert dan insert dijalankan dalam transaksi;
     * bila tetap bentrok (race), percobaan diulang dengan ID berikutnya.
     *
     * @param  array<string, mixed>  $atribut  nama, jenis_kelamin, asal_daerah, agama, umur, angkatan, status.
     * @param  int  $maksPercobaan  Jumlah percobaan sebelum menyerah.
     */
    public function buatAnakBaru(array $atribut, int $maksPercobaan = 5): Anak
    {
        $anakService = new AnakService;
        $data = $anakService->normalize($atribut);
        $data['status'] = $data['status'] ?: Anak::STATUS_AKTIF;

        $maksPercobaan = max(1, $maksPercobaan);
        $errorTerakhir = null;

        for ($percobaan = 1; $percobaan <= $maksPercobaan; $percobaan++) {
            $id = $anakService->generateId();

            // ID sudah dipakai baris lain (mis. baris sebelumnya pada batch ini).
            if (Anak::query()->whereKey($id)->exists()) {
                continue;
            }

            try {
                return DB::connection('semarak')->transaction(
                    fn (): Anak => Anak::query()->create(['id' => $id] + $data)
                );
            } catch (QueryException $e) {
                $errorTerakhir = $e;

                // Bentrok ID dari proses lain: coba nomor berikutnya.
                if (Anak::query()->whereKey($id)->exists()) {
                    continue;
                }

                throw $e;
            }
        }

        // Pesan tanpa PII: hanya jumlah percobaan + kode error SQL.
        throw new RuntimeException(
            'Gagal membuat ID anak yang unik setelah '.$maksPercobaan.' percobaan.'
            .($errorTerakhir !== null ? ' (kode database: '.$errorTerakhir->getCode().')' : '')
        );
    }

    /**
     * Buat akun klien `Draft` yang tertaut ke anak + profil + relasi.
     *
     * Dipakai alur import mode Remaja Binaan: email & kata sandi boleh NULL
     * sehingga akun belum bisa login sampai proses kelulusan.
     *
     * @param  array<string, mixed>  $profil  Data profil dari file (gender, birth_date, dst).
     * @param  array<int, int>  $pelatihan  ID master pelatihan.
     * @param  array<int, int>  $wilayah  ID master kota.
     */
    public function buatAkunDraft(
        Anak $anak,
        ?int $createdBy = null,
        ?string $email = null,
        array $profil = [],
        array $pelatihan = [],
        array $wilayah = [],
        bool $pekerjaanSaatIni = false,
    ): User {
        // PENGAMAN SKEMA: tanpa `users.anak_id` INSERT akun tertaut selalu gagal.
        if (! SchemaGuard::usersPunyaTautanAnak()) {
            throw new InvalidArgumentException(self::PESAN_MIGRASI_TERTUNDA);
        }

        if (User::query()->where('anak_id', $anak->getKey())->exists()) {
            throw new InvalidArgumentException("Anak {$anak->getKey()} sudah punya akun klien.");
        }

        $profilFinal = $profil;
        $profilFinal['gender'] = $profil['gender'] ?? self::petakanGender($anak->jenis_kelamin);
        $profilFinal['hire_status'] = $profil['hire_status'] ?? '0';

        $user = DB::transaction(function () use ($anak, $createdBy, $email, $profilFinal, $pelatihan, $wilayah, $pekerjaanSaatIni): User {
            $user = User::create([
                'name' => $anak->nama,
                'email' => $email,
                'password' => null,
                'user_type' => User::ROLE_CLIENT,
                'status' => UserStatus::Draft,
                'created_by' => $createdBy,
                'anak_id' => (string) $anak->getKey(),
            ]);

            $user->userProfile()->create($profilFinal);

            if ($pelatihan !== []) {
                $user->userTrainings()->attach(array_values(array_unique(array_map('intval', $pelatihan))));
            }

            if ($wilayah !== []) {
                $user->userWorkLocations()->attach(array_values(array_unique(array_map('intval', $wilayah))));
            }

            if ($pekerjaanSaatIni) {
                $user->userExperiences()->create([
                    'experience_job_name' => 'Bekerja Saat Ini',
                    'experience_description' => 'Di-import dari sistem',
                    'is_current_job' => true,
                    'sort_order' => 1,
                ]);
            }

            return $user;
        });

        // Log tanpa PII: hanya id anak, id akun, dan pelaku.
        Log::info('import.binaan.akun_draft', [
            'anak_id' => $anak->getKey(),
            'user_id' => $user->getKey(),
            'created_by' => $createdBy,
        ]);

        return $user;
    }

    /**
     * Hapus anak yang baru dibuat bila pembuatan akunnya gagal (kompensasi).
     *
     * Anak baru belum punya evaluasi/rapor, jadi cukup hapus satu baris ini.
     * Kegagalan kompensasi dicatat sebagai error agar operator memeriksa manual.
     */
    public function hapusAnakBaru(Anak|string $anak): void
    {
        $id = $anak instanceof Anak ? (string) $anak->getKey() : (string) $anak;

        try {
            Anak::query()->whereKey($id)->delete();

            Log::warning('import.binaan.kompensasi', ['anak_id' => $id]);
        } catch (\Throwable $e) {
            Log::error('import.binaan.kompensasi_gagal', [
                'anak_id' => $id,
                'error' => get_class($e),
            ]);
        }
    }
}
