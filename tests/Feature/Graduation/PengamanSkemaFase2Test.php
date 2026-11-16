<?php

namespace Tests\Feature\Graduation;

use App\Enums\UserStatus;
use App\Filament\Resources\AnakResource\Pages\ManageAnak;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Support\PeringatanMigrasi;
use App\Models\User;
use App\Modules\Absensi\Models\Anak;
use App\Services\GraduationService;
use App\Services\ImportService;
use App\Support\SchemaGuard;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Support\Absensi\AbsensiTestCase;

/**
 * PENGAMAN SKEMA Fase 2 — perilaku aplikasi bila migrasi
 * `2026_11_12_090000_add_anak_link_to_users_table` BELUM dijalankan.
 *
 * Insiden yang dikunci tes ini: `/admin/anak` error 500 karena kolom
 * `users.anak_id` belum ada, padahal kode Fase 3 sudah memakainya. Setelah
 * pengaman ditambahkan, halaman wajib tetap terbuka, fitur yang bergantung
 * pada kolom itu dinonaktifkan dengan pesan jelas, dan dashboard klien tetap
 * tampil memakai fallback berlabel "Angka sementara (belum final)".
 *
 * Kolom dilepas di dalam transaksi test (RefreshDatabase), sehingga skema
 * pulih otomatis setelah setiap test — termasuk untuk tes kondisi kolom
 * SUDAH ADA di bawah.
 */
class PengamanSkemaFase2Test extends AbsensiTestCase
{
    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Pengaman Skema',
            'email' => 'admin-pengaman-skema@example.test',
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_ADMIN,
            'status' => UserStatus::Published,
        ]);
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function klien(array $atribut = []): User
    {
        return User::create(array_merge([
            'name' => 'Klien Pengaman Skema',
            'email' => 'klien-'.uniqid().'@example.test',
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_CLIENT,
            'status' => UserStatus::Published,
        ], $atribut));
    }

    /**
     * Lepas kolom Fase 2 dari skema PER TEST, lalu buang cache SchemaGuard.
     */
    private function lepasKolomFase2(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['anak_id']);
            $table->dropIndex(['graduated_at']);
            $table->dropColumn(['anak_id', 'graduated_at']);
        });

        SchemaGuard::reset();

        $this->assertFalse(Schema::hasColumn('users', 'anak_id'));
        $this->assertFalse(Schema::hasColumn('users', 'graduated_at'));
    }

    public function test_halaman_anak_tetap_terbuka_tanpa_kolom_akun_klien(): void
    {
        $this->lepasKolomFase2();

        $this->actingAs($this->admin());

        $this->get('/admin/anak')
            ->assertOk()
            ->assertSee('Anak Binaan');

        $kolom = Livewire::test(ManageAnak::class)->instance()->getTable()->getColumns();

        $this->assertArrayNotHasKey('akun_klien', $kolom, 'Kolom Akun Klien tidak boleh dipasang.');
        $this->assertArrayNotHasKey('alamat_ktp', $kolom, 'Kolom Alamat hanya ada bila tautan akun tersedia.');
    }

    public function test_aksi_luluskan_dinonaktifkan_dengan_peringatan_migrasi(): void
    {
        $this->lepasKolomFase2();

        $this->actingAs($this->admin());

        Livewire::test(ManageAnak::class)
            ->assertTableActionDisabled('luluskan', 'AN-002')
            ->assertNotified(PeringatanMigrasi::PESAN);
    }

    public function test_service_kelulusan_menolak_dengan_pesan_migrasi_yang_jelas(): void
    {
        $this->lepasKolomFase2();

        $admin = $this->admin();
        $anak = Anak::query()->findOrFail('AN-002');

        try {
            app(GraduationService::class)->luluskan($anak, 'alumni@example.test', null, $admin->getKey());
            $this->fail('Kelulusan seharusnya ditolak saat kolom Fase 2 belum ada.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(GraduationService::PESAN_MIGRASI_TERTUNDA, $e->getMessage());
            $this->assertStringContainsString('Migrasi database', $e->getMessage());
            $this->assertStringNotContainsString('no such column', $e->getMessage());
        }

        $this->assertSame(Anak::STATUS_AKTIF, $anak->fresh()->status, 'Status anak tidak boleh berubah.');
        $this->assertSame(0, User::query()->where('user_type', User::ROLE_CLIENT)->count());
    }

    public function test_dashboard_klien_tetap_tampil_dengan_label_sementara(): void
    {
        $this->lepasKolomFase2();

        $this->actingAs($this->klien());

        $this->get('/klien')
            ->assertOk()
            ->assertSee('Jumlah Alumni')
            // Label boleh berkembang (mis. "Angka sementara (belum final)"),
            // yang penting penanda angka sementara tetap tampil.
            ->assertSee('Angka sementara');
    }

    public function test_daftar_user_tetap_terbuka_tanpa_kolom_fase_2(): void
    {
        $this->lepasKolomFase2();

        $admin = $this->admin();

        // Tabel memakai `deferLoading()`, sehingga baris dimuat lewat request
        // Livewire terpisah. Halaman tetap harus terbuka, lalu tabel dimuat.
        $this->actingAs($admin)->get('/admin/users')->assertOk();

        Livewire::test(ListUsers::class)
            ->loadTable()
            ->assertCanSeeTableRecords([$admin]);
    }

    public function test_import_data_tetap_jalan_dan_tidak_menautkan_anak(): void
    {
        $this->lepasKolomFase2();

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/import-data')->assertOk();

        $baris = [
            ['name' => 'Endang Sri Ayu', 'email' => 'endang.import@example.test', '_row_number' => 2],
        ];

        $peta = app(ImportService::class)->petaTautanAnak($baris);

        $this->assertSame([], $peta['peta'], 'Tanpa kolom anak_id tidak boleh ada rencana tautan.');
        $this->assertSame([], $peta['akan']);
        $this->assertStringContainsString('Migrasi database', $peta['tidak'][0]['alasan']);

        // Impor binaan harus tetap berhasil walau kolom tautan tidak ada.
        $berhasil = app(ImportService::class)->importData(
            $baris,
            [],
            $admin->getKey(),
            null,
            ImportService::MODE_BINAAN,
            true,
        );

        $this->assertSame(1, $berhasil['akun_dibuat'], 'Akun Draft harus tetap dibuat walau kolom Fase 2 belum ada.');
        $this->assertSame(0, $berhasil['anak_baru'], 'Tanpa kolom anak_id, data anak baru tidak dibuat (dibuat setelah migrasi).');
        $this->assertSame(0, $berhasil['gagal']);
        $this->assertDatabaseHas('users', ['email' => 'endang.import@example.test']);
    }

    public function test_login_dan_can_access_panel_tetap_berjalan(): void
    {
        $this->lepasKolomFase2();

        $admin = $this->admin();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'Rahasia#12345',
        ])->assertRedirect('/admin');

        $this->assertAuthenticated();

        $admin = $admin->fresh();

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($admin->canAccessPanel(Filament::getPanel('client')));
    }

    public function test_aksi_luluskan_aktif_dan_kolom_akun_klien_tampil_saat_kolom_sudah_ada(): void
    {
        $this->actingAs($this->admin());

        // Tabel memakai `deferLoading()`, sehingga header kolom baru muncul
        // setelah request Livewire `loadTable` — bukan di HTML awal.
        $this->get('/admin/anak')->assertOk();

        Livewire::test(ManageAnak::class)
            ->loadTable()
            ->assertSee('Akun Klien');

        $kolom = Livewire::test(ManageAnak::class)->instance()->getTable()->getColumns();

        $this->assertArrayHasKey('akun_klien', $kolom);

        Livewire::test(ManageAnak::class)
            ->assertTableActionEnabled('luluskan', 'AN-002')
            ->assertNotNotified(PeringatanMigrasi::PESAN);
    }
}
