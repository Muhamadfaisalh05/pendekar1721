<?php

namespace Tests\Feature\Security;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-TEST 07 — Kepemilikan profil tetap benar setelah `$fillable` dipasang
 * (user_id bukan atribut mass-assignable, lihat FASE 2).
 */
class ProfileOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function makeClient(string $email): User
    {
        return User::create([
            'name' => 'Nama ' . $email,
            'email' => $email,
            'password' => bcrypt('Secret#12345'),
            'user_type' => 'client',
            'status' => UserStatus::Published->value,
        ]);
    }

    public function test_profil_dibuat_lewat_relasi_mendapat_user_id_yang_benar(): void
    {
        $client = $this->makeClient('klien.profil@example.test');

        $profile = $client->userProfile()->make();
        $profile->fill(['description' => 'Profil baru'])->save();

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $client->id,
            'description' => 'Profil baru',
        ]);
    }

    public function test_klien_tanpa_profil_tetap_bisa_membuka_halaman_ubah_profil(): void
    {
        $client = $this->makeClient('klien.tanpa.profil@example.test');

        $this->actingAs($client)
            ->get('/klien/user-profile-edit-page')
            ->assertOk();
    }
}
