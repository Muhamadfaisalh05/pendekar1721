<?php

namespace Tests\Feature\Absensi;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Absensi\AbsensiTestCase;

class AbsensiFilamentTest extends AbsensiTestCase
{
    public function test_admin_dapat_membuka_resource_anak_dan_kegiatan(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Filament Absensi',
            'email' => 'admin-filament-absensi@example.test',
            'password' => Hash::make('password'),
            'user_type' => 'admin',
        ]);

        $this->actingAs($admin);

        $this->get('/admin/anak')->assertOk()->assertSee('Anak Binaan');
        $this->get('/admin/kegiatan')->assertOk()->assertSee('Uraian Kegiatan');
    }

    public function test_klien_tidak_bisa_membuka_resource_absensi(): void
    {
        $client = User::query()->create([
            'name' => 'Klien Filament Absensi',
            'email' => 'klien-filament-absensi@example.test',
            'password' => Hash::make('password'),
            'user_type' => 'client',
        ]);

        $this->actingAs($client)->get('/admin/anak')->assertForbidden();
    }
}
