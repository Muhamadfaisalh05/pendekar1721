<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserStatus;
use App\Models\User;
use App\Modules\Absensi\Models\Anak;
use App\Services\DasborAdminService;
use App\Support\SchemaGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Absensi\AbsensiTestCase;

/**
 * Dashboard Admin diuji dengan database SEMARAK sungguhan (SQLite in-memory,
 * skema parity) DAN tanpa kolom Fase 2 (`users.anak_id`, `users.graduated_at`).
 *
 * Pola "lepas kolom" mengikuti PengamanSkemaFase2Test: kolom dilepas di dalam
 * transaksi test (RefreshDatabase), jadi skema pulih otomatis.
 */
class AdminDashboardSchemaGuardTest extends AbsensiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        app(DasborAdminService::class)->lupakan();
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function pengguna(string $nama, string $email, string $tipe, UserStatus $status, array $atribut = []): User
    {
        return User::create(array_merge([
            'name' => $nama,
            'email' => $email,
            'password' => 'Rahasia#12345',
            'user_type' => $tipe,
            'status' => $status,
        ], $atribut));
    }

    private function lepasKolomFase2(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['anak_id']);
            $table->dropIndex(['graduated_at']);
            $table->dropColumn(['anak_id', 'graduated_at']);
        });

        SchemaGuard::reset();
    }

    public function test_dashboard_admin_tetap_200_dan_jujur_tanpa_kolom_fase_2(): void
    {
        $this->lepasKolomFase2();

        $admin = $this->pengguna('Admin Tanpa Kolom', 'admin.tanpa.kolom@example.test', User::ROLE_ADMIN, UserStatus::Published);
        $this->pengguna('Klien Tanpa Kolom', 'klien.tanpa.kolom@example.test', User::ROLE_CLIENT, UserStatus::Draft);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        // Bagian yang bergantung pada kolom Fase 2 harus jujur, bukan 500.
        $response->assertSee('Data belum tersedia');
        $response->assertSee('Perlu Tindakan');

        $ringkasan = app(DasborAdminService::class)->ringkasan();

        $this->assertTrue($ringkasan['perlu_tindakan']['migrasi_tertunda']);
        $this->assertNull($ringkasan['perlu_tindakan']['anak_tanpa_akun'], 'Tanpa users.anak_id, jumlah "tanpa akun" tidak bisa dihitung.');
        // SEMARAK tetap sehat pada test ini (yang hilang hanya kolom Fase 2).
        $this->assertIsInt($ringkasan['anak_aktif']);
        $this->assertSame(0, $ringkasan['siap_diluluskan'], 'Belum ada masa_pembinaan yang selesai.');
        $this->assertTrue($ringkasan['alumni']['belum_final'], 'Tanpa graduated_at, angka alumni wajib bertanda sementara.');
        $this->assertSame(1, $ringkasan['akun_draft']);
    }

    public function test_angka_binaan_diambil_dari_database_semarak(): void
    {
        $admin = $this->pengguna('Admin Semarak', 'admin.semarak@example.test', User::ROLE_ADMIN, UserStatus::Published);

        $aktif = (int) DB::connection('semarak')->table('anak')->where('status', Anak::STATUS_AKTIF)->count();
        $this->assertGreaterThan(0, $aktif, 'Fixture SEMARAK harus punya anak berstatus Aktif.');

        // Tautkan satu anak aktif ke akun klien → "tanpa akun" berkurang satu.
        $anak = DB::connection('semarak')->table('anak')->where('status', Anak::STATUS_AKTIF)->first();
        $this->pengguna('Klien Tertaut', 'klien.tertaut@example.test', User::ROLE_CLIENT, UserStatus::Published, [
            'anak_id' => $anak->id,
        ]);

        // Satu angkatan yang masa pembinaannya sudah selesai kemarin.
        DB::connection('semarak')->table('masa_pembinaan')->insert([
            'angkatan' => $anak->angkatan,
            'tanggal_selesai' => now()->subDay()->toDateString(),
        ]);

        $siap = (int) DB::connection('semarak')->table('anak')
            ->where('status', Anak::STATUS_AKTIF)
            ->where('angkatan', $anak->angkatan)
            ->count();

        $ringkasan = app(DasborAdminService::class)->ringkasan();

        $this->assertSame($aktif, $ringkasan['anak_aktif']);
        $this->assertSame($siap, $ringkasan['siap_diluluskan']);
        $this->assertSame($aktif - 1, $ringkasan['perlu_tindakan']['anak_tanpa_akun']);
        $this->assertFalse($ringkasan['perlu_tindakan']['migrasi_tertunda']);

        // Grafik batang: jumlah batang == jumlah data, dan totalnya == semua anak.
        $totalAnak = (int) DB::connection('semarak')->table('anak')->count();
        $this->assertCount(count($ringkasan['binaan_per_angkatan']['labels']), $ringkasan['binaan_per_angkatan']['data']);
        $this->assertLessThanOrEqual(10, count($ringkasan['binaan_per_angkatan']['labels']));
        $this->assertSame($totalAnak, array_sum($ringkasan['binaan_per_angkatan']['data']));

        // Panel "Perlu Tindakan" tampil lengkap untuk admin.
        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Anak Tanpa Akun Klien')
            ->assertDontSee('Belum dijalankan');
    }

    public function test_ringkasan_di_cache_lima_menit_dan_bisa_dilupakan(): void
    {
        $this->pengguna('Klien Cache', 'klien.cache@example.test', User::ROLE_CLIENT, UserStatus::Draft);

        $service = app(DasborAdminService::class);

        $this->assertSame(1, $service->ringkasan()['akun_draft']);

        // Angka masih dari cache: akun baru belum dihitung.
        $this->pengguna('Klien Cache Dua', 'klien.cache.dua@example.test', User::ROLE_CLIENT, UserStatus::Draft);
        $this->assertSame(1, $service->ringkasan()['akun_draft']);

        // Setelah cache dibuang, angka terbaru dipakai.
        $service->lupakan();
        $this->assertSame(2, $service->ringkasan()['akun_draft']);
    }
}
