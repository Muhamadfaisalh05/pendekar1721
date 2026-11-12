<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Modules\Absensi\Models\Anak;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            ->whereNull('anak_id')
            ->whereRaw('LOWER(name) = ?', [$nama])
            ->exists();
    }
}
