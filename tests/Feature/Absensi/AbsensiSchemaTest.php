<?php

namespace Tests\Feature\Absensi;

use App\Modules\Absensi\Models\Anak;
use App\Modules\Absensi\Models\Evaluasi;
use App\Modules\Absensi\Models\Kegiatan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Absensi\AbsensiTestCase;

class AbsensiSchemaTest extends AbsensiTestCase
{
    public function test_semua_tabel_parity_tersedia_di_koneksi_semarak(): void
    {
        foreach (['anak', 'kegiatan', 'evaluasi', 'jadwal_kegiatan', 'masa_pembinaan', 'catatan_rapor'] as $table) {
            $this->assertTrue(
                Schema::connection('semarak')->hasTable($table),
                "Tabel {$table} tidak ditemukan di koneksi semarak."
            );
        }
    }

    public function test_state_awal_sama_dengan_dump_native(): void
    {
        $this->assertSame(6, Anak::query()->count());
        $this->assertSame(22, Kegiatan::query()->count());
        $this->assertSame(50, Evaluasi::query()->count());
    }

    public function test_relasi_anak_dan_evaluasi_berjalan(): void
    {
        $anak = Anak::query()->findOrFail('AN-001');

        $this->assertSame('Endang Sri Ayu', $anak->nama);
        $this->assertSame(9, $anak->evaluasi()->count());
        $this->assertSame('Aku Bangun Pagi Sendiri Jam 04.30', $anak->evaluasi()->first()->kegiatan->nama);
    }

    public function test_nilai_di_luar_enum_ditolak(): void
    {
        $this->expectException(QueryException::class);

        Evaluasi::query()->create([
            'anak_id' => 'AN-001',
            'kegiatan_id' => 22,
            'tanggal' => '2026-09-24',
            'nilai' => 'hebat',
        ]);
    }

    public function test_kombinasi_anak_kegiatan_tanggal_unik(): void
    {
        $this->expectException(QueryException::class);

        Evaluasi::query()->create([
            'anak_id' => 'AN-001',
            'kegiatan_id' => 1,
            'tanggal' => '2026-09-24',
            'nilai' => 'sesuai',
        ]);
    }

    public function test_hapus_anak_menghapus_evaluasinya(): void
    {
        DB::connection('semarak')->statement('PRAGMA foreign_keys = ON');

        $before = Evaluasi::query()->where('anak_id', 'AN-002')->count();
        $this->assertGreaterThan(0, $before);

        Anak::query()->findOrFail('AN-002')->delete();

        $this->assertSame(0, Evaluasi::query()->where('anak_id', 'AN-002')->count());
    }

    public function test_timezone_aplikasi_asia_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
    }
}
