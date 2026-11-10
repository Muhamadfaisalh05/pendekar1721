<?php

namespace Tests\Feature\Security;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-TEST 01 — Profil publik hanya boleh menampilkan user Published.
 * Regresi yang dijaga: Draft/Archived tidak bisa dibuka via /profile/{slug}.
 */
class PublicProfileVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeClient(UserStatus $status, string $name): User
    {
        $user = User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.test',
            'password' => bcrypt('Secret#12345'),
            'user_type' => 'client',
            'status' => $status->value,
        ]);

        // Profil dibuat lewat relasi (user_id bukan atribut mass-assignable).
        $user->userProfile()->create([
            'description' => 'Deskripsi ' . $name,
        ]);

        return $user;
    }

    public function test_profil_published_bisa_diakses_publik(): void
    {
        $user = $this->makeClient(UserStatus::Published, 'Peserta Publik');

        $this->get('/profile/peserta-publik-' . $user->id)
            ->assertOk()
            ->assertSee('Peserta Publik');
    }

    public function test_profil_draft_tidak_bisa_diakses_via_url(): void
    {
        $user = $this->makeClient(UserStatus::Draft, 'Peserta Draft');

        $this->get('/profile/peserta-draft-' . $user->id)->assertNotFound();
    }

    public function test_profil_archived_tidak_bisa_diakses_via_url(): void
    {
        $user = $this->makeClient(UserStatus::Archived, 'Peserta Arsip');

        $this->get('/profile/peserta-arsip-' . $user->id)->assertNotFound();
    }

    public function test_profil_admin_tidak_bisa_diakses_publik(): void
    {
        $admin = User::create([
            'name' => 'Admin Internal',
            'email' => 'admin.internal@example.test',
            'password' => bcrypt('Secret#12345'),
            'user_type' => 'admin',
            'status' => UserStatus::Published->value,
        ]);

        $this->get('/profile/admin-internal-' . $admin->id)->assertNotFound();
    }

    public function test_slug_tanpa_id_ditolak_404(): void
    {
        $this->get('/profile/tanpa-id')->assertNotFound();
    }
}
