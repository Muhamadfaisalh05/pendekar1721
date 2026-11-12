<?php

namespace App\Filament\Support;

use App\Support\SchemaGuard;
use Filament\Notifications\Notification;

/**
 * Peringatan panel Admin bila migrasi Fase 2 (`users.anak_id`,
 * `users.graduated_at`) belum dijalankan.
 *
 * Penyebabnya nyata: halaman `/admin/anak` pernah error 500 karena kode
 * memakai kolom yang belum dimigrasikan. Peringatan ini memberi tahu operator
 * bahwa ada fitur yang dinonaktifkan sementara, bukan kerusakan data.
 *
 * Dikirim SEKALI per request (`SchemaGuard::bolehKirimPeringatanMigrasi()`)
 * agar beberapa komponen Livewire pada halaman yang sama tidak menumpuk
 * notifikasi.
 */
final class PeringatanMigrasi
{
    public const PESAN = 'Ada migrasi database yang belum dijalankan. Hubungi administrator sistem.';

    public static function tampilkanSekali(): void
    {
        if (! SchemaGuard::bolehKirimPeringatanMigrasi()) {
            return;
        }

        Notification::make()
            ->title(self::PESAN)
            ->warning()
            ->persistent()
            ->send();
    }
}
