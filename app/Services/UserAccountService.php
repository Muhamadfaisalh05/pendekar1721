<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * FASE 3 — operasi akun klien yang dijalankan administrator dari panel.
 *
 * Dua tugas:
 * 1. Reset kata sandi akun. Plaintext dikembalikan SEKALI ke pemanggil untuk
 *    ditampilkan di modal; tidak pernah ditulis ke database maupun log.
 * 2. Guard hapus akun: admin tidak boleh menghapus akun sendiri, dan
 *    administrator terakhir tidak boleh dihapus (supaya panel tidak terkunci).
 *
 * Aturan sengaja ditaruh di service, bukan hanya di tombol Filament, agar tetap
 * berlaku bila nanti ada jalur lain (tinker, console command, API).
 */
final class UserAccountService
{
    /**
     * Panjang kata sandi acak hasil reset.
     *
     * Disamakan dengan `GraduationService` agar panjang kata sandi sementara
     * konsisten di seluruh alur onboarding.
     */
    public const PANJANG_PASSWORD = 24;

    /**
     * Reset kata sandi akun secara acak.
     *
     * @return string Kata sandi baru (plaintext) — tampilkan sekali, jangan disimpan.
     */
    public function resetPassword(User $user, ?int $actorId = null): string
    {
        $plainPassword = Str::password(self::PANJANG_PASSWORD);

        // Cast `hashed` pada User::password akan meng-hash nilai ini.
        $user->forceFill(['password' => $plainPassword])->save();

        Log::info('akun.reset_password', [
            'user_id' => $user->getKey(),
            'user_type' => $user->user_type,
            'status' => $user->status?->value,
            'actor_id' => $actorId,
        ]);

        return $plainPassword;
    }

    /**
     * Alasan akun tidak boleh dihapus, atau NULL bila boleh.
     */
    public function alasanTidakBolehDihapus(User $user, ?User $aktor): ?string
    {
        if ($aktor === null) {
            return 'Tidak ada pengguna yang sedang login.';
        }

        if (! $aktor->isAdmin()) {
            return 'Hanya Administrator Pendekar yang boleh menghapus akun.';
        }

        if ($aktor->getKey() === $user->getKey()) {
            return 'Anda tidak dapat menghapus akun sendiri.';
        }

        if ($this->adalahAdminTerakhir($user)) {
            return 'Administrator terakhir tidak boleh dihapus. Angkat administrator lain terlebih dahulu.';
        }

        return null;
    }

    /**
     * Boleh dihapus oleh aktor ini?
     */
    public function bolehDihapus(User $user, ?User $aktor): bool
    {
        return $this->alasanTidakBolehDihapus($user, $aktor) === null;
    }

    /**
     * Apakah akun ini satu-satunya administrator yang masih bisa membuka panel?
     *
     * Akun berstatus `Archived` tidak dihitung karena tidak bisa login lagi;
     * status NULL (data lama) tetap dihitung agar tidak mengunci panel secara
     * tak sengaja.
     */
    public function adalahAdminTerakhir(User $user): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        return ! User::query()
            ->where('user_type', User::ROLE_ADMIN)
            ->whereKeyNot($user->getKey())
            ->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhere('status', '!=', UserStatus::Archived->value);
            })
            ->exists();
    }

    /**
     * Hapus akun setelah guard lolos.
     *
     * @throws InvalidArgumentException bila guard menolak penghapusan.
     */
    public function hapus(User $user, ?User $aktor): void
    {
        $alasan = $this->alasanTidakBolehDihapus($user, $aktor);

        if ($alasan !== null) {
            throw new InvalidArgumentException($alasan);
        }

        $user->delete();

        Log::warning('akun.dihapus', [
            'user_id' => $user->getKey(),
            'user_type' => $user->user_type,
            'anak_id' => $user->anak_id,
            'actor_id' => $aktor?->getKey(),
        ]);
    }
}
