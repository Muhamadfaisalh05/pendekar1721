<?php

namespace Tests\Support\Absensi;

use Illuminate\Support\Facades\DB;

/**
 * Menyiapkan koneksi `semarak` (SQLite in-memory) untuk pengujian modul absensi.
 *
 * - Migrasi parity dijalankan sekali per proses test.
 * - Data awal diambil dari fixture `tests/Fixtures/Absensi/seed/state.json`
 *   (hasil ekspor `semarak/db/semarak.sql`) dan dimasukkan di dalam transaksi
 *   test aktif, sehingga otomatis di-rollback setiap selesai test.
 */
trait PreparesAbsensiDatabase
{
    /**
     * Migrasi parity (sekali per proses) + reset data ke state awal dump native.
     *
     * Migrasi dijalankan langsung dari file migrasi (bukan `artisan migrate`)
     * karena pada SQLite `:memory:` perintah artisan memakai koneksi PDO baru
     * sehingga skema yang dibuat tidak terlihat oleh koneksi test.
     */
    protected function prepareAbsensiDatabase(): void
    {
        // Idempotent: migrasi memakai guard `hasTable()`, jadi aman dijalankan
        // setiap test (koneksi SQLite in-memory bisa dibuat ulang oleh framework).
        foreach (glob(database_path('migrations/semarak/*.php')) as $file) {
            $migration = require $file;
            $migration->up();
        }

        $this->seedAbsensiState();
    }

    /**
     * Masukkan state awal (dari dump native) ke koneksi `semarak`.
     */
    protected function seedAbsensiState(): void
    {
        $path = dirname(__DIR__, 2).'/Fixtures/Absensi/seed/state.json';
        $state = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $connection = DB::connection('semarak');

        $connection->table('evaluasi')->delete();
        $connection->table('catatan_rapor')->delete();
        $connection->table('jadwal_kegiatan')->delete();
        $connection->table('kegiatan')->delete();
        $connection->table('anak')->delete();
        $connection->table('masa_pembinaan')->delete();

        foreach (array_chunk($state['anak'], 100) as $chunk) {
            $connection->table('anak')->insert($chunk);
        }

        foreach (array_chunk($state['kegiatan'], 100) as $chunk) {
            $connection->table('kegiatan')->insert($chunk);
        }

        foreach (array_chunk($state['evaluasi'], 100) as $chunk) {
            $connection->table('evaluasi')->insert($chunk);
        }
    }
}
