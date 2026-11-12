<?php

namespace Tests\Feature\Security;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-TEST 05 — Status akun mengikat login & akses panel.
 *
 * Regresi yang dijaga:
 * - `Draft` (belum diaktifkan) tidak boleh login dan tidak boleh membuka panel.
 * - `Archived` tetap ditolak seperti sebelumnya.
 * - `Published` tetap bisa login & membuka panel sesuai role.
 * - `status = null` (data lama) tetap diizinkan agar tidak mengunci akun lama.
 */
class AccountStatusLoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email, ?UserStatus $status, string $type = User::ROLE_CLIENT): User
    {
        return User::create([
            'name' => 'Uji '.$status?->value,
            'email' => $email,
            'password' => 'Rahasia#12345',
            'user_type' => $type,
            'status' => $status,
        ]);
    }

    public function test_akun_draft_tidak_bisa_login_dan_mendapat_pesan_aktivasi(): void
    {
        $this->makeUser('draft.login@example.test', UserStatus::Draft);

        $this->post('/login', [
            'email' => 'draft.login@example.test',
            'password' => 'Rahasia#12345',
        ])->assertInvalid([
            'email' => 'Akun Anda belum diaktifkan. Silakan hubungi administrator Pendekar 1721.',
        ]);

        $this->assertGuest();
    }

    public function test_akun_arsip_tetap_ditolak_dengan_pesan_arsip(): void
    {
        $this->makeUser('arsip.login@example.test', UserStatus::Archived);

        $this->post('/login', [
            'email' => 'arsip.login@example.test',
            'password' => 'Rahasia#12345',
        ])->assertInvalid([
            'email' => 'Akun Anda sudah diarsipkan dan tidak dapat digunakan. Silakan hubungi administrator.',
        ]);

        $this->assertGuest();
    }

    public function test_akun_published_bisa_login(): void
    {
        $this->makeUser('aktif.login@example.test', UserStatus::Published);

        $this->post('/login', [
            'email' => 'aktif.login@example.test',
            'password' => 'Rahasia#12345',
        ])->assertRedirect('/klien');

        $this->assertAuthenticated();
    }

    public function test_akun_tanpa_status_tetap_bisa_login(): void
    {
        $this->makeUser('tanpa.status@example.test', null);

        $this->post('/login', [
            'email' => 'tanpa.status@example.test',
            'password' => 'Rahasia#12345',
        ])->assertRedirect('/klien');

        $this->assertAuthenticated();
    }

    public function test_klien_draft_tidak_bisa_membuka_panel_klien(): void
    {
        $client = $this->makeUser('draft.panel@example.test', UserStatus::Draft);

        $this->actingAs($client)->get('/klien')->assertForbidden();
    }

    public function test_admin_draft_tidak_bisa_membuka_panel_admin(): void
    {
        $admin = $this->makeUser('draft.admin@example.test', UserStatus::Draft, User::ROLE_ADMIN);

        $this->actingAs($admin)->get('/admin')->assertForbidden();
    }

    public function test_klien_arsip_tidak_bisa_membuka_panel_klien(): void
    {
        $client = $this->makeUser('arsip.panel@example.test', UserStatus::Archived);

        $this->actingAs($client)->get('/klien')->assertForbidden();
    }

    public function test_akun_published_tanpa_kata_sandi_tidak_bisa_login(): void
    {
        // Binaan hasil import dibuat dengan `password = NULL` (FASE 2):
        // guard harus menolak SEBELUM Hash::check dan memakai pesan gagal
        // yang sama seperti kredensial salah, tanpa membocorkan status akun.
        User::create([
            'name' => 'Binaan Tanpa Sandi',
            'email' => 'tanpa.sandi@example.test',
            'password' => null,
            'user_type' => User::ROLE_CLIENT,
            'status' => UserStatus::Published,
        ]);

        $this->post('/login', [
            'email' => 'tanpa.sandi@example.test',
            'password' => 'apa-saja-123',
        ])->assertInvalid([
            'email' => 'Email atau kata sandi tidak sesuai. Mohon periksa kembali lalu coba lagi.',
        ]);

        $this->assertGuest();
    }

    public function test_klien_published_tetap_bisa_membuka_panel_klien(): void
    {
        $client = $this->makeUser('aktif.panel@example.test', UserStatus::Published);

        $this->actingAs($client)->get('/klien')->assertOk();
    }
}
