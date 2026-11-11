<?php

namespace Tests\Feature\Absensi;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Absensi\AbsensiTestCase;

class AbsensiPagesTest extends AbsensiTestCase
{
    public function test_semua_halaman_tampil_untuk_admin(): void
    {
        $this->actingAs($this->adminUser());

        $pages = [
            'absensi.dashboard' => 'Pantau perkembangan anak',
            'absensi.input' => 'Input Evaluasi Karakter',
            'absensi.anak' => 'Daftar Anak Binaan',
            'absensi.summary' => 'Summary &amp; Analisis Individu',
            'absensi.rapor' => 'Cetak Dokumen Rapor',
            'absensi.pengaturan' => 'Pengaturan Jadwal Kegiatan',
        ];

        foreach ($pages as $routeName => $expectedText) {
            $response = $this->get(route($routeName));
            $response->assertOk();
            $response->assertSee($expectedText, false);
            $response->assertSee('SEMARAK', false);
        }
    }

    public function test_halaman_rapor_memuat_kop_resmi(): void
    {
        $this->actingAs($this->adminUser());

        $this->get(route('absensi.rapor'))
            ->assertOk()
            ->assertSee('PEMERINTAH PROVINSI JAWA BARAT')
            ->assertSee('UPTD PUSAT PELAYANAN SOSIAL GRIYA BINA REMAJA', false)
            ->assertSee('Jalan Maribaya No. 22 Lembang');
    }

    public function test_menu_navigasi_menghubungkan_kedua_aplikasi(): void
    {
        $this->actingAs($this->adminUser());

        $this->get(route('absensi.dashboard'))
            ->assertOk()
            ->assertSee(route('home'), false)
            ->assertSee(route('absensi.pengaturan'), false);
    }

    public function test_header_tidak_lagi_menampilkan_menu_navigasi_dan_label_role(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('absensi.dashboard'))
            ->assertOk()
            ->assertDontSee('absensi-nav', false)
            ->assertDontSee('absensi-user-chip', false)
            ->assertDontSee('Administrator Pendekar')
            ->assertDontSee('Admin Absensi (SEMARAK)')
            ->assertSee('Keluar');
    }

    public function test_tamu_tidak_bisa_membuka_halaman(): void
    {
        $this->get(route('absensi.anak'))->assertRedirect();
    }

    public function test_klien_tidak_bisa_membuka_halaman(): void
    {
        $client = User::query()->create([
            'name' => 'Klien Halaman',
            'email' => 'klien-halaman@example.test',
            'password' => Hash::make('password'),
            'user_type' => 'client',
        ]);

        $this->actingAs($client)->get(route('absensi.anak'))->assertForbidden();
    }

    private function adminUser(): User
    {
        return User::query()->create([
            'name' => 'Admin Halaman',
            'email' => 'admin-halaman@example.test',
            'password' => Hash::make('password'),
            'user_type' => 'admin',
        ]);
    }
}
