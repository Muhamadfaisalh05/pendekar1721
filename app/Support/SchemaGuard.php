<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Penjaga skema kolom Fase 2 (`users.anak_id`, `users.graduated_at`).
 *
 * Kode Fase 2/Fase 3 (tautan akun klien, kelulusan, statistik alumni) memakai
 * dua kolom tersebut, sedangkan migrasinya bisa belum dijalankan di sebuah
 * environment — pernah terjadi di dev: `/admin/anak` error 500 dengan pesan
 * SQL "no such column: users.anak_id". Semua pemakaian kolom wajib lewat
 * helper ini supaya aplikasi tetap terbuka dan hanya fitur terkait yang
 * dinonaktifkan, bukan seluruh halaman.
 *
 * Hasil pengecekan disimpan di properti static, yaitu cache PER REQUEST —
 * sengaja bukan cache lintas request (`Cache::remember`). Begitu migrasi
 * dijalankan, request berikutnya langsung melihat kolom baru tanpa
 * `cache:clear` dan tanpa deploy ulang.
 */
final class SchemaGuard
{
    /** Cache per request: `users.anak_id` sudah ada? */
    private static ?bool $usersPunyaTautanAnak = null;

    /** Cache per request: `users.graduated_at` sudah ada? */
    private static ?bool $usersPunyaTanggalLulus = null;

    /** Peringatan migrasi sudah dikirim pada request ini? */
    private static bool $peringatanMigrasiSudahDikirim = false;

    /**
     * Kolom `users.anak_id` (tautan akun klien ke `semarak.anak`) tersedia?
     */
    public static function usersPunyaTautanAnak(): bool
    {
        return self::$usersPunyaTautanAnak ??= Schema::hasColumn('users', 'anak_id');
    }

    /**
     * Kolom `users.graduated_at` (penanda resmi alumni) tersedia?
     */
    public static function usersPunyaTanggalLulus(): bool
    {
        return self::$usersPunyaTanggalLulus ??= Schema::hasColumn('users', 'graduated_at');
    }

    /**
     * Kedua kolom Fase 2 tersedia → fitur kelulusan/alumni boleh aktif.
     */
    public static function usersPunyaKolomAlumni(): bool
    {
        return self::usersPunyaTautanAnak() && self::usersPunyaTanggalLulus();
    }

    /**
     * Migrasi Fase 2 tertunda (salah satu atau kedua kolom belum ada)?
     */
    public static function migrasiFase2Tertunda(): bool
    {
        return ! self::usersPunyaKolomAlumni();
    }

    /**
     * Boleh mengirim peringatan migrasi sekarang?
     *
     * Sekali per request, supaya beberapa komponen Livewire pada halaman yang
     * sama tidak memunculkan notifikasi berulang. Tidak pernah mengirim apa
     * pun bila kolom sudah lengkap.
     */
    public static function bolehKirimPeringatanMigrasi(): bool
    {
        if (self::$peringatanMigrasiSudahDikirim || ! self::migrasiFase2Tertunda()) {
            return false;
        }

        self::$peringatanMigrasiSudahDikirim = true;

        return true;
    }

    /**
     * Lupakan hasil pengecekan (dipakai tes: skema bisa berubah antar test).
     */
    public static function reset(): void
    {
        self::$usersPunyaTautanAnak = null;
        self::$usersPunyaTanggalLulus = null;
        self::$peringatanMigrasiSudahDikirim = false;
    }
}
