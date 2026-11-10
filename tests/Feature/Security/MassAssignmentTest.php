<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-TEST 05 — Mass assignment (FASE 2).
 *
 * Regresi yang dijaga: Model::unguard() global tidak aktif lagi sehingga
 * hanya atribut di $fillable yang bisa diisi dari input pengguna.
 */
class MassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_unguard_global_sudah_nonaktif(): void
    {
        $this->assertFalse(Model::isUnguarded());
    }

    public function test_atribut_tidak_dikenal_tidak_ikut_terisi(): void
    {
        $user = new User();
        $user->fill([
            'name' => 'Peserta Uji',
            'user_type' => 'client',
            'is_admin' => true,
        ]);

        $this->assertArrayNotHasKey('is_admin', $user->getAttributes());
        $this->assertSame('Peserta Uji', $user->name);
    }

    public function test_user_id_profil_tidak_bisa_dibelokkan_lewat_mass_assignment(): void
    {
        $profile = new UserProfile();
        $profile->fill([
            'user_id' => 999999,
            'description' => 'Deskripsi uji',
        ]);

        $this->assertArrayNotHasKey('user_id', $profile->getAttributes());
        $this->assertSame('Deskripsi uji', $profile->description);
    }
}
