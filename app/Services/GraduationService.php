<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Modules\Absensi\Models\Anak;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * FASE 2 — kelulusan binaan menjadi akun alumni (Klien aktif).
 *
 * Memenuhi empat syarat wajib yang dicatat sebelum refactor
 * (docs/BACKUP-LOGIC-SEBELUM-REFACTOR.md §11):
 *
 * 1. Email diperiksa unik dengan MENGABAIKAN akun yang sudah tertaut ke anak
 *    itu, sehingga kelulusan ulang / perbaikan data tidak ditolak sebagai
 *    "email sudah dipakai".
 * 2. Kegagalan di tengah proses dikompensasi: status akun dikembalikan ke
 *    status SEBELUMNYA (bukan selalu `Draft`), dan akun yang baru dibuat
 *    dihapus. Alasan dicatat di `Log::error('graduation.rollback', [...])`.
 * 3. Kata sandi acak dikembalikan SEKALI lewat `['password' => ...]`. Tidak
 *    pernah disimpan sebagai plaintext di DB maupun log.
 * 4. Akun binaan tidak punya kata sandi (NULL) sampai di sini; login menolak
 *    password NULL (lihat LoginController).
 *
 * Perubahan status `semarak.anak` tidak bisa ikut dalam transaksi database
 * aplikasi (koneksi berbeda), karena itu kompensasi eksplisit dipakai.
 */
final class GraduationService
{
    /** Panjang kata sandi acak yang diberikan ke alumni. */
    private const PANJANG_PASSWORD = 24;

    public function __construct(private readonly ImportBinaanService $binaan) {}

    /**
     * Luluskan seorang anak dan aktifkan akun kliennya.
     *
     * @param  CarbonInterface|string|null  $tanggalLulus  default: sekarang
     * @param  int|null  $actorId  id admin yang menjalankan aksi (untuk audit)
     * @return array{
     *     user: User,
     *     password: string|null,
     *     akun_dibuat: bool,
     *     status_anak_sebelum: string,
     *     status_akun_sebelum: string|null,
     *     graduated_at: Carbon
     * }
     *
     * @throws InvalidArgumentException bila email tidak valid atau sudah dipakai akun lain
     */
    public function luluskan(
        Anak $anak,
        string $email,
        CarbonInterface|string|null $tanggalLulus = null,
        ?int $actorId = null,
    ): array {
        $email = Str::lower(trim($email));
        $this->pastikanEmailValid($email);

        $user = User::query()->where('anak_id', $anak->getKey())->first();
        $dibuat = false;

        $this->pastikanEmailBebas($email, $user);

        $statusAnakSebelum = (string) $anak->status;
        $graduatedAt = $tanggalLulus === null ? Carbon::now() : Carbon::parse($tanggalLulus);

        if ($user === null) {
            // Satu jalur pembuatan akun saja: lewat jembatan ImportBinaanService.
            // Nama kembar sengaja diizinkan karena admin sudah memilih anak ini
            // secara eksplisit.
            $hasil = $this->binaan->pastikanAkunBinaan($anak, $actorId, izinkanNamaGanda: true);
            $user = $hasil['user'];
            $dibuat = $hasil['dibuat'];

            if ($user === null) {
                throw new InvalidArgumentException(
                    "Akun binaan untuk anak {$anak->getKey()} tidak dapat dibuat."
                );
            }
        }

        $statusUserSebelum = $user->status;

        // Nilai pembanding untuk kompensasi. Ditulis eksplisit agar keempat
        // atribut (termasuk yang belum ada di `$attributes`) pasti dipulihkan.
        $atributUserSebelum = [
            'email' => $user->email,
            'password' => $user->getRawOriginal('password'),
            'status' => $user->status,
            'graduated_at' => $user->graduated_at,
        ];

        $plainPassword = null;

        try {
            DB::transaction(function () use ($user, $email, $graduatedAt, $anak, &$plainPassword): void {
                $perubahan = [
                    'email' => $email,
                    'status' => UserStatus::Published,
                    'graduated_at' => $graduatedAt,
                ];

                // Akun yang belum pernah punya kata sandi (binaan Draft) diberi
                // kata sandi acak. Akun yang sudah punya kata sandi tidak direset
                // diam-diam saat diluluskan.
                if (blank($user->password)) {
                    $plainPassword = Str::password(self::PANJANG_PASSWORD);
                    $perubahan['password'] = $plainPassword;
                }

                $user->forceFill($perubahan)->save();

                if (! $user->userProfile()->exists()) {
                    $user->userProfile()->create([
                        'gender' => ImportBinaanService::petakanGender($anak->jenis_kelamin),
                    ]);
                }
            });

            // Ditulis setelah transaksi aplikasi sukses; kegagalan di sini
            // memicu kompensasi status akun.
            if ($statusAnakSebelum !== Anak::STATUS_LULUS) {
                $anak->forceFill(['status' => Anak::STATUS_LULUS])->save();
            }
        } catch (Throwable $e) {
            $this->kompensasi($user, $dibuat, $atributUserSebelum, $anak, $statusAnakSebelum, $e);

            throw $e;
        }

        Log::info('graduation.luluskan', [
            'anak_id' => $anak->getKey(),
            'user_id' => $user->getKey(),
            'akun_dibuat' => $dibuat,
            'status_akun_sebelum' => $statusUserSebelum?->value,
            'status_anak_sebelum' => $statusAnakSebelum,
            'actor_id' => $actorId,
            'password_baru' => $plainPassword !== null,
        ]);

        return [
            'user' => $user->refresh(),
            'password' => $plainPassword,
            'akun_dibuat' => $dibuat,
            'status_anak_sebelum' => $statusAnakSebelum,
            'status_akun_sebelum' => $statusUserSebelum?->value,
            'graduated_at' => $graduatedAt,
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    private function pastikanEmailValid(string $email): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Email tidak valid: \"{$email}\".");
        }
    }

    /**
     * Email wajib unik, tetapi akun yang sudah tertaut ke anak ini diabaikan
     * (syarat §11 butir 1) supaya kelulusan ulang tidak gagal.
     *
     * @throws InvalidArgumentException
     */
    private function pastikanEmailBebas(string $email, ?User $user): void
    {
        $bentrok = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->when($user !== null, fn ($query) => $query->whereKeyNot($user->getKey()))
            ->first();

        if ($bentrok !== null) {
            throw new InvalidArgumentException(
                "Email {$email} sudah dipakai akun lain (#{$bentrok->getKey()}). Gunakan email lain."
            );
        }
    }

    /**
     * Kompensasi kegagalan (syarat §11 butir 2).
     *
     * - Akun yang baru dibuat pada proses ini: dihapus bersama profilnya.
     * - Akun yang sudah ada: email, kata sandi, `graduated_at`, dan status
     *   dikembalikan ke NILAI SEBELUMNYA (`Published`, `Archived`, atau
     *   `null`), bukan dipaksa `Draft`.
     * - Status anak dikembalikan bila sempat berubah.
     *
     * @param  array<string, mixed>  $atributUserSebelum
     */
    private function kompensasi(
        User $user,
        bool $dibuat,
        array $atributUserSebelum,
        Anak $anak,
        string $statusAnakSebelum,
        Throwable $penyebab,
    ): void {
        $catatan = [
            'anak_id' => $anak->getKey(),
            'user_id' => $user->getKey(),
            'akun_dibuat' => $dibuat,
            'error' => $penyebab->getMessage(),
        ];

        try {
            if ($dibuat) {
                $user->userProfile()->delete();
                $user->delete();
                $catatan['kompensasi_akun'] = 'dihapus';
            } else {
                // `password` berupa hash; cast `hashed` tidak me-rehash nilai
                // yang sudah berupa hash (Hash::isHashed()).
                $user->forceFill($atributUserSebelum)->saveQuietly();
                $catatan['kompensasi_akun'] = 'atribut_dipulihkan';
            }

            if ($statusAnakSebelum !== Anak::STATUS_LULUS) {
                $anak->forceFill(['status' => $statusAnakSebelum])->save();
                $catatan['kompensasi_anak'] = 'status_dipulihkan='.$statusAnakSebelum;
            }
        } catch (Throwable $e) {
            $catatan['kompensasi_gagal'] = $e->getMessage();
        }

        Log::error('graduation.rollback', $catatan);
    }
}
