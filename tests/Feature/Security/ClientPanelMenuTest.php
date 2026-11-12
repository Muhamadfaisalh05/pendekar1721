<?php

namespace Tests\Feature\Security;

use App\Enums\UserStatus;
use App\Filament\Client\Resources\AlumniResource;
use App\Filament\Client\Resources\TrainingResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-TEST 07 — Menu & batas panel Klien setelah refactor (Fase 1).
 *
 * Panel Klien bersifat read-only + self-service profil:
 * - Dashboard & Data Pelatihan boleh dibuka.
 * - CRUD/import peserta sudah dihapus total (route tidak ada → 404).
 * - Direktori alumni opsional (default mati → 404).
 */
class ClientPanelMenuTest extends TestCase
{
    use RefreshDatabase;

    private function makeClient(): User
    {
        return User::create([
            'name' => 'Klien Panel Menu',
            'email' => 'klien.menu@example.test',
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_CLIENT,
            'status' => UserStatus::Published->value,
        ]);
    }

    public function test_dashboard_klien_bisa_dibuka(): void
    {
        $this->actingAs($this->makeClient())->get('/klien')->assertOk();
    }

    public function test_halaman_peserta_sudah_tidak_ada(): void
    {
        $client = $this->makeClient();

        $this->actingAs($client)->get('/klien/pesertas')->assertNotFound();
        $this->actingAs($client)->get('/klien/pesertas/create')->assertNotFound();
        $this->actingAs($client)->get('/klien/pesertas/1/edit')->assertNotFound();
    }

    public function test_halaman_import_peserta_sudah_tidak_ada(): void
    {
        $this->actingAs($this->makeClient())->get('/klien/import-peserta')->assertNotFound();
    }

    public function test_data_pelatihan_read_only_bisa_dibuka(): void
    {
        $client = $this->makeClient();

        $this->actingAs($client)->get('/klien/trainings')->assertOk();

        $this->assertFalse(TrainingResource::canCreate());
        $this->assertFalse(TrainingResource::canDeleteAny());
    }

    public function test_halaman_ubah_profil_bisa_dibuka(): void
    {
        $this->actingAs($this->makeClient())->get('/klien/user-profile-edit-page')->assertOk();
    }

    public function test_direktori_alumni_404_saat_config_mati(): void
    {
        config()->set('pendekar.alumni.directory_enabled', false);

        $client = $this->makeClient();

        $this->actingAs($client)->get('/klien/alumni')->assertNotFound();
        $this->assertFalse(AlumniResource::shouldRegisterNavigation());
    }

    public function test_direktori_alumni_read_only_saat_config_hidup(): void
    {
        config()->set('pendekar.alumni.directory_enabled', true);

        $client = $this->makeClient();

        $this->actingAs($client)->get('/klien/alumni')->assertOk();
        $this->assertTrue(AlumniResource::shouldRegisterNavigation());
        $this->assertFalse(AlumniResource::canCreate());
        $this->assertFalse(AlumniResource::canDeleteAny());
    }

    public function test_admin_tetap_tidak_bisa_membuka_panel_klien(): void
    {
        $admin = User::create([
            'name' => 'Admin Panel Menu',
            'email' => 'admin.menu@example.test',
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_ADMIN,
            'status' => UserStatus::Published->value,
        ]);

        $this->actingAs($admin)->get('/klien')->assertForbidden();
        $this->actingAs($admin)->get('/klien/trainings')->assertForbidden();
    }
}
