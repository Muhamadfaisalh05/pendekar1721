<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserStatus;
use App\Models\MasterTraining;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard Klien (`/klien`) setelah desain ulang.
 *
 * Fokus tes: sapaan & status akun, kartu ringkasan, persentase kelengkapan
 * profil (angka + daftar kolom kosong), daftar pelatihan milik sendiri, dan
 * akses cepat yang dibatasi (direktori alumni mengikuti config).
 */
class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function klien(string $nama, string $email, array $atribut = []): User
    {
        return User::create(array_merge([
            'name' => $nama,
            'email' => $email,
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_CLIENT,
            'status' => UserStatus::Published,
        ], $atribut));
    }

    public function test_dashboard_klien_menyapa_pemilik_akun_dan_menampilkan_status(): void
    {
        $saya = $this->klien('Siti Aminah Rahmawati', 'siti.dashboard@example.test');

        $this->actingAs($saya)->get('/klien')
            ->assertOk()
            ->assertSee('Selamat Datang')
            ->assertSee('Siti Aminah Rahmawati')
            ->assertSee('Status akun')
            ->assertSee('Aktif');
    }

    public function test_dashboard_klien_tidak_memuat_data_klien_lain(): void
    {
        $saya = $this->klien('Pemilik Dashboard Sendiri', 'pemilik.sendiri@example.test');
        $lain = $this->klien('Orang Lain Rahasia Sekali', 'orang.lain@example.test');

        $pelatihan = MasterTraining::create(['title' => 'Pelatihan Menjahit']);
        $lain->userTrainings()->attach($pelatihan->getKey());

        $response = $this->actingAs($saya)->get('/klien');

        $response->assertOk();
        $response->assertSee('Pemilik Dashboard Sendiri');
        $response->assertDontSee('Orang Lain Rahasia Sekali');
        $response->assertDontSee('orang.lain@example.test');
        // Pelatihan milik orang lain tidak boleh muncul di daftar "Pelatihan Saya".
        $response->assertDontSee('Pelatihan Menjahit');
    }

    public function test_persentase_kelengkapan_profil_dan_daftar_kolom_kosong(): void
    {
        $saya = $this->klien('Profil Setengah Jadi', 'profil.setengah@example.test');

        // 2 dari 10 field (nama + email) sudah otomatis terisi = 20%.
        $this->actingAs($saya)->get('/klien')
            ->assertOk()
            ->assertSee('20%')
            ->assertSee('Tanggal lahir')
            ->assertSee('Foto profil');

        // Tambah 2 field lagi → 40% dan kedua label itu hilang dari daftar kosong.
        $saya->userProfile()->create([
            'gender' => 'Perempuan',
            'birth_place' => 'Cimahi',
        ]);

        $response = $this->actingAs($saya->fresh())->get('/klien');

        $response->assertOk();
        $response->assertSee('40%');
        $response->assertDontSee('Tempat lahir');
        $response->assertSee('Tanggal lahir');
    }

    public function test_pelatihan_saya_read_only_dan_keadaan_kosong_yang_ramah(): void
    {
        $saya = $this->klien('Klien Tanpa Pelatihan', 'tanpa.pelatihan@example.test');

        $this->actingAs($saya)->get('/klien')
            ->assertOk()
            ->assertSee('Pelatihan Saya')
            ->assertSee('Belum ada data pelatihan');

        $pelatihan = MasterTraining::create(['title' => 'Pelatihan Servis Motor']);
        $saya->userTrainings()->attach($pelatihan->getKey());

        $this->actingAs($saya->fresh())->get('/klien')
            ->assertOk()
            ->assertSee('Pelatihan Servis Motor')
            ->assertDontSee('Belum ada data pelatihan');
    }

    public function test_urutan_bagian_dashboard_konsisten(): void
    {
        $saya = $this->klien('Klien Urutan Bagian', 'urutan.bagian@example.test');

        $html = $this->actingAs($saya)->get('/klien')->assertOk()->getContent();

        // Hero (sapaan) harus di atas kartu ringkasan, lalu pelatihan, lalu akses cepat.
        // Penjaga regresi: kartu ringkasan pernah tidak sengaja tersisip ke dalam hero.
        $posisiHero = mb_strpos($html, 'Selamat Datang');
        $posisiKartu = mb_strpos($html, 'Jumlah Alumni');
        $posisiPelatihan = mb_strpos($html, 'Data pelatihan diisi oleh admin');
        $posisiAksesCepat = mb_strpos($html, 'Riwayat pelatihan yang Anda ikuti');

        $this->assertNotFalse($posisiHero);
        $this->assertNotFalse($posisiKartu);
        $this->assertNotFalse($posisiPelatihan);
        $this->assertNotFalse($posisiAksesCepat);

        $this->assertTrue($posisiHero < $posisiKartu, 'Hero harus dirender sebelum kartu ringkasan.');
        $this->assertTrue($posisiKartu < $posisiPelatihan, 'Kartu ringkasan harus dirender sebelum daftar pelatihan.');
        $this->assertTrue($posisiPelatihan < $posisiAksesCepat, 'Daftar pelatihan harus dirender sebelum akses cepat.');
    }

    public function test_akses_cepat_hanya_profil_dan_pelatihan_saat_direktori_alumni_mati(): void
    {
        config()->set('pendekar.alumni.directory_enabled', false);

        $saya = $this->klien('Klien Akses Cepat', 'akses.cepat@example.test');

        $response = $this->actingAs($saya)->get('/klien');

        $response->assertOk();
        $response->assertSee('Akses Cepat');
        $response->assertSee('Ubah Profil');
        $response->assertSee('Data Pelatihan');
        $response->assertDontSee('/klien/alumni', false);
    }

    public function test_tautan_alumni_muncul_hanya_saat_config_diaktifkan(): void
    {
        config()->set('pendekar.alumni.directory_enabled', true);

        $saya = $this->klien('Klien Alumni Direktori', 'alumni.direktori@example.test');

        $this->actingAs($saya)->get('/klien')
            ->assertOk()
            ->assertSee('/klien/alumni', false);
    }
}
