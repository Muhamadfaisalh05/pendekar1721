<?php

namespace Tests\Feature\Security;

use App\Enums\UserStatus;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * FASE 3 — Reset Kata Sandi + guard hapus akun di panel admin.
 *
 * Regresi yang dijaga:
 * - reset kata sandi menghasilkan kata sandi acak yang hanya ditampilkan sekali;
 * - admin tidak bisa menghapus akun sendiri;
 * - administrator terakhir tidak bisa dihapus (panel tidak boleh terkunci);
 * - akun non-admin tidak berhak menghapus akun lain;
 * - tombol hapus disembunyikan pada dua kasus terlarang di atas.
 */
class UserAccountGuardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $email = 'admin.guard@example.test', UserStatus $status = UserStatus::Published): User
    {
        return User::create([
            'name' => 'Admin Guard',
            'email' => $email,
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_ADMIN,
            'status' => $status,
        ]);
    }

    private function klien(string $email = 'klien.guard@example.test'): User
    {
        return User::create([
            'name' => 'Klien Guard',
            'email' => $email,
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_CLIENT,
            'status' => UserStatus::Published,
        ]);
    }

    public function test_reset_password_mengganti_hash_dan_plaintext_dikembalikan_sekali(): void
    {
        $klien = $this->klien();

        $plainPassword = app(UserAccountService::class)->resetPassword($klien, null);

        $this->assertIsString($plainPassword);
        $this->assertSame(UserAccountService::PANJANG_PASSWORD, strlen($plainPassword));
        $this->assertTrue(Hash::check($plainPassword, $klien->fresh()->password));
        $this->assertFalse(Hash::check('Rahasia#12345', $klien->fresh()->password));
        $this->assertNotSame($plainPassword, $klien->fresh()->getRawOriginal('password'));
    }

    public function test_aksi_reset_password_di_panel_menampilkan_kata_sandi_sekali(): void
    {
        $admin = $this->admin();
        $klien = $this->klien();

        $this->actingAs($admin);

        $komponen = Livewire::test(ListUsers::class)
            ->callTableAction('resetPassword', $klien->getKey())
            ->assertHasNoTableActionErrors()
            ->assertSet('mountedTableActions', ['kataSandiSekali']);

        $plainPassword = $komponen->get('mountedTableActionsArguments.0.password');

        $this->assertIsString($plainPassword);
        $this->assertTrue(Hash::check($plainPassword, $klien->fresh()->password));
        $komponen->assertSee($plainPassword);
    }

    public function test_tidak_bisa_menghapus_akun_sendiri(): void
    {
        $admin = $this->admin();
        $service = app(UserAccountService::class);

        $this->assertFalse($service->bolehDihapus($admin, $admin));
        $this->assertStringContainsString('akun sendiri', (string) $service->alasanTidakBolehDihapus($admin, $admin));

        try {
            $service->hapus($admin, $admin);
            $this->fail('Menghapus akun sendiri seharusnya ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('akun sendiri', $e->getMessage());
        }

        $this->assertNotNull($admin->fresh());
    }

    public function test_administrator_terakhir_tidak_boleh_dihapus(): void
    {
        $admin = $this->admin();
        $adminArsip = $this->admin('admin.arsip@example.test', UserStatus::Archived);
        $service = app(UserAccountService::class);

        // Admin arsip tidak bisa login, jadi tidak dihitung sebagai cadangan.
        $this->assertTrue($service->adalahAdminTerakhir($admin));
        $this->assertFalse($service->bolehDihapus($admin, $this->klien()));

        try {
            $service->hapus($admin, $adminArsip);
            $this->fail('Administrator terakhir seharusnya tidak bisa dihapus.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Administrator terakhir', $e->getMessage());
        }

        $this->assertNotNull($admin->fresh());

        // Dengan admin aktif kedua, penghapusan diizinkan.
        $adminKedua = $this->admin('admin.kedua@example.test');

        $this->assertFalse($service->adalahAdminTerakhir($admin));
        $this->assertTrue($service->bolehDihapus($admin, $adminKedua));

        $service->hapus($admin, $adminKedua);

        $this->assertNull($admin->fresh());
        $this->assertNotNull($adminKedua->fresh());
    }

    public function test_hanya_admin_yang_boleh_menghapus_akun_lain(): void
    {
        $admin = $this->admin();
        $klien = $this->klien();
        $service = app(UserAccountService::class);

        $this->assertFalse($service->bolehDihapus($klien, $klien));
        $this->assertFalse($service->bolehDihapus($klien, null));
        $this->assertTrue($service->bolehDihapus($klien, $admin));
    }

    public function test_tombol_hapus_disembunyikan_untuk_akun_sendiri_dan_administrator_terakhir(): void
    {
        $admin = $this->admin();
        $klien = $this->klien();

        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('delete', $admin->getKey())
            ->assertTableActionVisible('delete', $klien->getKey())
            ->assertTableActionVisible('resetPassword', $klien->getKey());
    }
}
