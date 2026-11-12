<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\DasborAdminService;
use App\Support\StatistikAlumni;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Dashboard Admin (`/admin`) setelah desain ulang.
 *
 * Yang dikunci tes ini:
 * - halaman tetap 200 (tidak error 500) walau database SEMARAK belum siap;
 * - TIDAK memuat nama/email pengguna lain (hanya angka agregat);
 * - angka ringkasan benar dan konsisten dengan definisi alumni;
 * - klien tidak boleh membuka panel Admin.
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ringkasan dashboard di-cache 5 menit; store `array` bertahan dalam
        // satu proses test, jadi cache dibersihkan sebelum setiap test.
        Cache::flush();
        app(DasborAdminService::class)->lupakan();
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function pengguna(string $nama, string $email, string $tipe, UserStatus $status): User
    {
        return User::create([
            'name' => $nama,
            'email' => $email,
            'password' => 'Rahasia#12345',
            'user_type' => $tipe,
            'status' => $status,
        ]);
    }

    public function test_dashboard_admin_terbuka_untuk_admin(): void
    {
        $admin = $this->pengguna('Admin Dasbor Pendekar', 'admin.dasbor@example.test', User::ROLE_ADMIN, UserStatus::Published);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Anak Binaan Aktif')
            ->assertSee('Siap Diluluskan')
            ->assertSee('Akun Draft')
            ->assertSee('Alumni')
            ->assertSee('Total Akun Klien')
            ->assertSee('Perlu Tindakan')
            ->assertSee('Akses Cepat');
    }

    public function test_dashboard_admin_tidak_memuat_nama_atau_email_pengguna_lain(): void
    {
        $admin = $this->pengguna('Admin Rahasia Aman', 'admin.rahasia@example.test', User::ROLE_ADMIN, UserStatus::Published);

        $this->pengguna('Nama Klien Sangat Rahasia', 'klien.rahasia@example.test', User::ROLE_CLIENT, UserStatus::Published);
        $this->pengguna('Admin Kedua Rahasia', 'admin.kedua@example.test', User::ROLE_ADMIN, UserStatus::Published);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertDontSee('Nama Klien Sangat Rahasia');
        $response->assertDontSee('klien.rahasia@example.test');
        $response->assertDontSee('Admin Kedua Rahasia');
        $response->assertDontSee('admin.kedua@example.test');
    }

    public function test_angka_ringkasan_sesuai_data(): void
    {
        $this->pengguna('Admin Ringkasan', 'admin.ringkasan@example.test', User::ROLE_ADMIN, UserStatus::Published);
        $this->pengguna('Klien Satu', 'klien.satu@example.test', User::ROLE_CLIENT, UserStatus::Published);
        $this->pengguna('Klien Dua', 'klien.dua@example.test', User::ROLE_CLIENT, UserStatus::Draft);
        $this->pengguna('Klien Tiga', 'klien.tiga@example.test', User::ROLE_CLIENT, UserStatus::Draft);
        $this->pengguna('Klien Arsip', 'klien.arsip@example.test', User::ROLE_CLIENT, UserStatus::Archived);

        $ringkasan = app(DasborAdminService::class)->ringkasan();

        $this->assertSame(2, $ringkasan['akun_draft']);
        $this->assertSame(4, $ringkasan['total_klien']);
        $this->assertSame(1, $ringkasan['admin']);

        // Koneksi SEMARAK belum punya tabel pada test ini → "belum tersedia".
        $this->assertNull($ringkasan['anak_aktif']);
        $this->assertNull($ringkasan['siap_diluluskan']);
        $this->assertNull($ringkasan['binaan_per_angkatan']);

        // Definisi alumni sama dengan dashboard klien.
        $this->assertSame(StatistikAlumni::hitung(), $ringkasan['alumni']);

        // Grafik pertumbuhan selalu 12 titik (bulan berjalan di akhir).
        $this->assertCount(12, $ringkasan['pertumbuhan']['labels']);
        $this->assertCount(12, $ringkasan['pertumbuhan']['data']);
        $this->assertSame(4, array_sum($ringkasan['pertumbuhan']['data']));

        // Status akun: 3 status selalu dikirim untuk legenda grafik.
        $this->assertSame(
            [UserStatus::Draft->getLabel(), UserStatus::Published->getLabel(), UserStatus::Archived->getLabel()],
            $ringkasan['komposisi_status']['labels']
        );
        $this->assertSame([2, 1, 1], $ringkasan['komposisi_status']['data']);
    }

    public function test_dashboard_admin_menampilkan_data_belum_tersedia_tanpa_database_semarak(): void
    {
        $admin = $this->pengguna('Admin Tanpa Semarak', 'admin.tanpa.semarak@example.test', User::ROLE_ADMIN, UserStatus::Published);

        // Pesan jujur lebih baik daripada angka 0 yang menyesatkan.
        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Data belum tersedia');
    }

    public function test_klien_tidak_bisa_membuka_dashboard_admin(): void
    {
        $klien = $this->pengguna('Klien Dilarang', 'klien.dilarang@example.test', User::ROLE_CLIENT, UserStatus::Published);

        $this->actingAs($klien)->get('/admin')->assertForbidden();
    }
}
