<?php

namespace Tests\Feature\Graduation;

use App\Enums\UserStatus;
use App\Filament\Client\Pages\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 4 — kartu "Jumlah Alumni" di dashboard klien.
 *
 * Definisi resmi (disetujui pemilik produk):
 *
 *     user_type = 'client' AND status = 'published' AND graduated_at IS NOT NULL
 *
 * Fallback sementara: selama **belum satu pun** klien `published` punya
 * `graduated_at` (backfill belum berjalan), kartu memakai definisi lama
 * (`client` + `published`) dan menampilkan penanda "Angka sementara".
 * Begitu satu `graduated_at` terisi, angka otomatis memakai `alumni()->count()`
 * dan label tersebut hilang.
 */
class AlumniStatistikDashboardTest extends TestCase
{
    use RefreshDatabase;

    private const TANGGAL_LULUS = '2026-06-30 08:00:00';

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function klien(array $atribut = []): User
    {
        return User::create(array_merge([
            'name' => 'Klien Uji',
            'email' => 'klien-'.uniqid().'@example.test',
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_CLIENT,
            'status' => UserStatus::Published->value,
        ], $atribut));
    }

    public function test_kartu_memakai_definisi_lama_dan_label_sementara_saat_belum_ada_graduated_at(): void
    {
        $klien = $this->klien();
        $this->klien();                                                      // published, belum lulus
        $this->klien(['status' => UserStatus::Draft->value]);
        $this->klien(['status' => UserStatus::Archived->value]);

        $this->actingAs($klien);

        $statistik = app(Dashboard::class)->getStatistik();

        $this->assertSame(
            2,
            $statistik['total_alumni'],
            'Fallback menghitung definisi lama: client + published (tanpa melihat graduated_at).'
        );
        $this->assertTrue($statistik['total_alumni_belum_final']);

        $response = $this->get('/klien');

        $response->assertOk();
        $response->assertSee('Jumlah Alumni');
        // Label penanda angka sementara (teks persisnya mengikuti desain kartu).
        $response->assertSee('Angka sementara');
    }

    public function test_kartu_otomatis_beralih_ke_definisi_resmi_setelah_satu_graduated_at_diisi(): void
    {
        $klien = $this->klien();
        $this->klien();

        $this->actingAs($klien);

        $sebelum = app(Dashboard::class)->getStatistik();

        $this->assertSame(2, $sebelum['total_alumni']);
        $this->assertTrue($sebelum['total_alumni_belum_final']);

        $klien->forceFill(['graduated_at' => self::TANGGAL_LULUS])->save();

        $this->actingAs($klien->fresh());

        $sesudah = app(Dashboard::class)->getStatistik();

        $this->assertSame(1, $sesudah['total_alumni'], 'Hanya akun ber-graduated_at yang dihitung.');
        $this->assertSame(User::query()->alumni()->count(), $sesudah['total_alumni']);
        $this->assertFalse($sesudah['total_alumni_belum_final'], 'Label sementara harus hilang otomatis.');

        $response = $this->get('/klien');

        $response->assertOk();
        $response->assertDontSee('Angka sementara');
        $response->assertDontSee('belum final');
    }

    public function test_kartu_memakai_definisi_resmi_tanpa_label_saat_sudah_ada_alumni(): void
    {
        $alumni = $this->klien(['graduated_at' => self::TANGGAL_LULUS]);

        $this->klien();                                                      // published, belum lulus
        $this->klien(['status' => UserStatus::Draft->value, 'graduated_at' => self::TANGGAL_LULUS]);
        $this->klien(['status' => UserStatus::Archived->value, 'graduated_at' => self::TANGGAL_LULUS]);
        $this->klien([
            'user_type' => User::ROLE_ADMIN,
            'graduated_at' => self::TANGGAL_LULUS,
        ]);

        $this->actingAs($alumni);

        $statistik = app(Dashboard::class)->getStatistik();

        $this->assertSame(1, $statistik['total_alumni']);
        $this->assertSame(User::query()->alumni()->count(), $statistik['total_alumni']);
        $this->assertFalse($statistik['total_alumni_belum_final']);

        $response = $this->get('/klien');

        $response->assertOk();
        $response->assertSee('Jumlah Alumni');
        $response->assertDontSee('Angka sementara');
    }

    public function test_jumlah_alumni_nol_dan_masih_sementara_bila_belum_ada_klien_published(): void
    {
        $this->klien(['status' => UserStatus::Draft->value]);

        $statistik = app(Dashboard::class)->getStatistik();

        $this->assertSame(0, $statistik['total_alumni']);
        $this->assertTrue($statistik['total_alumni_belum_final']);
    }
}
