<?php

namespace Tests\Feature\Security;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-TEST 06 — Dashboard Klien tidak membocorkan data pribadi user lain (F-01).
 *
 * Regresi yang dijaga: `Dashboard::getPesertaTerbaru()` dulu menampilkan
 * nama + email semua user `client` tanpa filter status/created_by.
 * Sekarang dashboard hanya menampilkan angka agregat & data milik sendiri.
 */
class ClientDashboardPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function makeClient(string $name, string $email): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_CLIENT,
            'status' => UserStatus::Published->value,
        ]);
    }

    public function test_dashboard_klien_tidak_menampilkan_nama_atau_email_klien_lain(): void
    {
        $saya = $this->makeClient('Klien Pemilik Akun', 'pemilik.akun@example.test');

        // Dibuat setelah akun pemilik agar (bila query lama dipakai) muncul paling atas.
        $this->makeClient('Nama Rahasia Klien Lain', 'rahasia.klien.lain@example.test');

        $response = $this->actingAs($saya)->get('/klien');

        $response->assertOk();
        $response->assertSee('Klien Pemilik Akun');
        $response->assertDontSee('Nama Rahasia Klien Lain');
        $response->assertDontSee('rahasia.klien.lain@example.test');
    }

    public function test_dashboard_klien_tidak_menampilkan_email_klien_draft(): void
    {
        $saya = $this->makeClient('Klien Aktif Dashboard', 'aktif.dashboard@example.test');

        User::create([
            'name' => 'Binaan Draft Rahasia',
            'email' => 'binaan.draft.rahasia@example.test',
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_CLIENT,
            'status' => UserStatus::Draft->value,
        ]);

        $response = $this->actingAs($saya)->get('/klien');

        $response->assertOk();
        $response->assertDontSee('Binaan Draft Rahasia');
        $response->assertDontSee('binaan.draft.rahasia@example.test');
    }

    public function test_dashboard_klien_menampilkan_kartu_ringkasan_tanpa_daftar_peserta(): void
    {
        $saya = $this->makeClient('Klien Kartu Ringkasan', 'kartu.ringkasan@example.test');

        $response = $this->actingAs($saya)->get('/klien');

        $response->assertOk();
        $response->assertSee('Jumlah Alumni');
        $response->assertSee('Pelatihan Saya');
        $response->assertSee('Kelengkapan Profil');

        // Blok lama yang membocorkan data orang lain harus hilang.
        $response->assertDontSee('Data Peserta Terbaru');
        $response->assertDontSee('Aksi Cepat');
    }
}
