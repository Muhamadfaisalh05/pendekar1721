<?php

namespace App\Support;

use App\Enums\UserStatus;
use App\Models\User;

/**
 * Satu-satunya tempat menghitung angka "Jumlah Alumni".
 *
 * Definisi resmi (disetujui pemilik produk, lihat docs/BACKUP-LOGIC-SEBELUM-
 * REFACTOR.md §11): `user_type = 'client'` AND `status = 'published'` AND
 * `graduated_at IS NOT NULL` — yaitu `User::scopeAlumni()`.
 *
 * FALLBACK SEMENTARA: selama **belum satu pun** klien `published` punya
 * `graduated_at` (backfill belum berjalan) maupun kolom itu belum ada
 * (migrasi Fase 2 tertunda), angka memakai definisi lama `client` +
 * `published` dan ditandai `belum_final = true` sehingga UI menampilkan
 * "Angka sementara (belum final)".
 *
 * Dipakai bersama oleh dashboard Klien dan dashboard Admin supaya kedua
 * panel tidak pernah menampilkan angka alumni yang berbeda.
 */
final class StatistikAlumni
{
    /**
     * @return array{total: int, belum_final: bool}
     */
    public static function hitung(): array
    {
        if (! SchemaGuard::usersPunyaTanggalLulus()) {
            return ['total' => self::klienPublished(), 'belum_final' => true];
        }

        $alumni = User::query()->alumni()->count();

        if ($alumni > 0) {
            return ['total' => $alumni, 'belum_final' => false];
        }

        return ['total' => self::klienPublished(), 'belum_final' => true];
    }

    /**
     * Definisi lama sebagai jembatan: `client` + `published` (tanpa
     * `graduated_at`).
     */
    public static function klienPublished(): int
    {
        return User::query()
            ->where('user_type', User::ROLE_CLIENT)
            ->where('status', UserStatus::Published->value)
            ->count();
    }
}
