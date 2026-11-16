<?php

namespace Tests\Feature\Performance;

use App\Enums\UserStatus;
use App\Filament\Client\Resources\AlumniResource\Pages\ListAlumnis;
use App\Filament\Resources\AnakResource\Pages\ManageAnak;
use App\Filament\Resources\KegiatanResource\Pages\ManageKegiatan;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\Absensi\AbsensiTestCase;

/**
 * PERF-TEST 01 — navigasi panel Admin & Klien (Commit B).
 *
 * Yang dikunci oleh tes ini:
 * - kedua panel Filament memakai mode SPA Livewire (`wire:navigate`);
 * - tabel yang berat memakai `deferLoading()`, sehingga HTML awal tidak
 *   membawa header/baris tabel, lalu dimuat lewat request `loadTable`;
 * - setelah `loadTable`, isi tabel tetap tampil normal.
 *
 * Panel Absensi bukan panel Filament (UI native), jadi tidak diuji di sini.
 */
class PanelNavigationPerfTest extends AbsensiTestCase
{
    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Navigasi Cepat',
            'email' => 'admin.navigasi@example.test',
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_ADMIN,
            'status' => UserStatus::Published,
        ]);
    }

    private function klien(): User
    {
        return User::create([
            'name' => 'Klien Navigasi Cepat',
            'email' => 'klien.navigasi@example.test',
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_CLIENT,
            'status' => UserStatus::Published,
        ]);
    }

    public function test_panel_admin_dan_klien_memakai_mode_spa(): void
    {
        $this->assertTrue(Filament::getPanel('admin')->hasSpaMode());
        $this->assertTrue(Filament::getPanel('client')->hasSpaMode());
    }

    public function test_tabel_anak_ditunda_lalu_memuat_kolom_akun_klien(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/anak')
            ->assertOk()
            ->assertSee('wire:init="loadTable"', false)
            ->assertDontSee('fi-ta-header-cell', false);

        Livewire::test(ManageAnak::class)
            ->loadTable()
            ->assertSee('Akun Klien')
            ->assertSee('Anak Binaan');
    }

    public function test_tabel_kegiatan_ditunda_lalu_memuat_kolom_uraian(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/kegiatan')
            ->assertOk()
            ->assertSee('wire:init="loadTable"', false)
            ->assertDontSee('fi-ta-header-cell', false);

        Livewire::test(ManageKegiatan::class)
            ->loadTable()
            ->assertSee('Uraian Kegiatan');
    }

    public function test_tabel_user_ditunda_lalu_memuat_baris_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin);

        $this->get('/admin/users')
            ->assertOk()
            ->assertSee('wire:init="loadTable"', false)
            ->assertDontSee('fi-ta-header-cell', false);

        Livewire::test(ListUsers::class)
            ->loadTable()
            ->assertCanSeeTableRecords([$admin]);
    }

    public function test_tabel_alumni_klien_ditunda_lalu_memuat_kolom_nama(): void
    {
        config()->set('pendekar.alumni.directory_enabled', true);

        $alumni = $this->klien();
        $alumni->forceFill(['graduated_at' => now()])->save();

        $this->actingAs($alumni);

        $this->get('/klien/alumni')
            ->assertOk()
            ->assertSee('wire:init="loadTable"', false)
            ->assertDontSee('fi-ta-header-cell', false);

        Livewire::test(ListAlumnis::class)
            ->loadTable()
            ->assertSee('Nama')
            ->assertSee($alumni->name);
    }
}
