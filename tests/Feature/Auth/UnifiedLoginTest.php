<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RoleAccountSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Absensi\AbsensiTestCase;

/**
 * Satu halaman login (`/login`) untuk tiga role:
 * admin -> /admin, admin_absensi -> /absensi, client -> /klien.
 */
class UnifiedLoginTest extends AbsensiTestCase
{
    private function makeUser(string $type, string $email, UserStatus $status = UserStatus::Published): User
    {
        return User::query()->create([
            'name' => 'Uji '.$type,
            'email' => $email,
            'password' => 'Rahasia#12345',
            'user_type' => $type,
            'status' => $status,
        ]);
    }

    public function test_halaman_login_tampil_untuk_tamu(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('PENDEKAR1721')
            ->assertSee('UPTD Pusat Pelayanan Sosial Griya Bina Remaja')
            ->assertSee('Direktori profil tenaga kerja terampil')
            ->assertSee('images/hero-ppsgbr.png')
            ->assertDontSee('Masuk ke akun Anda')
            ->assertDontSee('Satu login,')
            ->assertDontSee('Akun demo')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false);
    }

    public function test_url_login_panel_lama_sudah_tidak_tersedia(): void
    {
        // Guest harus selalu memakai satu pintu: /login.
        $this->get('/admin/login')->assertNotFound();
        $this->get('/klien/login')->assertNotFound();
    }

    public function test_tamu_panel_diarahkan_ke_login_terpadu(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/klien')->assertRedirect('/login');
    }

    public function test_admin_diarahkan_ke_panel_admin(): void
    {
        $this->makeUser(User::ROLE_ADMIN, 'admin.login@example.test');

        $this->post('/login', [
            'email' => 'admin.login@example.test',
            'password' => 'Rahasia#12345',
        ])->assertRedirect('/admin');

        $this->assertAuthenticated();
        $this->get('/admin')->assertOk();
    }

    public function test_admin_absensi_diarahkan_ke_modul_absensi(): void
    {
        $user = $this->makeUser(User::ROLE_ADMIN_ABSENSI, 'absensi.login@example.test');

        $this->post('/login', [
            'email' => 'absensi.login@example.test',
            'password' => 'Rahasia#12345',
        ])->assertRedirect('/absensi');

        $this->actingAs($user)->get('/absensi')->assertOk();
        $this->actingAs($user)->get('/absensi/input')->assertOk();
    }

    public function test_admin_absensi_tidak_bisa_membuka_panel_admin(): void
    {
        $user = $this->makeUser(User::ROLE_ADMIN_ABSENSI, 'absensi.panel@example.test');

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_klien_diarahkan_ke_panel_klien(): void
    {
        $user = $this->makeUser(User::ROLE_CLIENT, 'klien.login@example.test');

        $this->post('/login', [
            'email' => 'klien.login@example.test',
            'password' => 'Rahasia#12345',
        ])->assertRedirect('/klien');

        $this->actingAs($user)->get('/klien')->assertOk();
    }

    public function test_klien_tidak_bisa_membuka_modul_absensi(): void
    {
        $user = $this->makeUser(User::ROLE_CLIENT, 'klien.absensi@example.test');

        $this->actingAs($user)->get('/absensi')->assertForbidden();
        $this->actingAs($user)->getJson('/absensi/api?action=anak')->assertForbidden();
    }

    public function test_admin_biasa_tetap_bisa_membuka_modul_absensi(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN, 'admin.absensi@example.test');

        $this->actingAs($admin)->get('/absensi')->assertOk();
    }

    public function test_password_salah_ditolak(): void
    {
        $this->makeUser(User::ROLE_ADMIN, 'admin.salah@example.test');

        $this->from('/login')
            ->post('/login', [
                'email' => 'admin.salah@example.test',
                'password' => 'password-salah',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'email' => 'Email atau kata sandi tidak sesuai. Mohon periksa kembali lalu coba lagi.',
            ]);

        $this->assertGuest();
    }

    public function test_akun_arsip_tidak_bisa_login(): void
    {
        $this->makeUser(User::ROLE_ADMIN, 'admin.arsip@example.test', UserStatus::Archived);

        $this->from('/login')
            ->post('/login', [
                'email' => 'admin.arsip@example.test',
                'password' => 'Rahasia#12345',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'email' => 'Akun Anda sudah diarsipkan dan tidak dapat digunakan. Silakan hubungi administrator.',
            ]);

        $this->assertGuest();
    }

    public function test_logout_mengakhiri_sesi_dan_kembali_ke_login(): void
    {
        $user = $this->makeUser(User::ROLE_ADMIN, 'admin.logout@example.test');

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    /**
     * Regresi: tombol Keluar di panel Filament pernah menghasilkan 500 karena
     * redirect bawaan Filament membangun navigasi panel setelah sesi berakhir
     * (canAccess() dievaluasi tanpa user).
     */
    public function test_logout_panel_filament_kembali_ke_login_tanpa_error(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN, 'admin.panel.logout@example.test');
        $klien = $this->makeUser(User::ROLE_CLIENT, 'klien.panel.logout@example.test');

        $this->actingAs($admin)
            ->post('/admin/logout')
            ->assertRedirect('/login');

        $this->assertGuest();

        $this->actingAs($klien)
            ->post('/klien/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_pengguna_yang_sudah_login_diarahkan_dari_halaman_login(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN, 'admin.ulang@example.test');
        $absensi = $this->makeUser(User::ROLE_ADMIN_ABSENSI, 'absensi.ulang@example.test');
        $klien = $this->makeUser(User::ROLE_CLIENT, 'klien.ulang@example.test');

        $this->actingAs($admin)->get('/login')->assertRedirect('/admin');

        // Middleware AuthenticateSession memutus sesi bila hash kata sandi
        // berbeda (simulasi: masing-masing pengguna memakai browser sendiri).
        $this->flushSession();

        $this->actingAs($absensi)->get('/login')->assertRedirect('/absensi');

        $this->flushSession();

        $this->actingAs($klien)->get('/login')->assertRedirect('/klien');
    }

    public function test_tamu_diarahkan_ke_login_terpadu(): void
    {
        $this->get('/absensi')->assertRedirect('/login');
        $this->get('/absensi/api?action=anak')->assertRedirect('/login');
    }

    public function test_seeder_role_membuat_tiga_akun_siap_pakai(): void
    {
        $this->seed(RoleAccountSeeder::class);

        $this->assertSame(3, User::query()->count());
        $this->assertNotNull(User::query()->where('email', 'admin@pendekar1721.test')
            ->where('user_type', User::ROLE_ADMIN)->first());
        $this->assertNotNull(User::query()->where('email', 'absensi@pendekar1721.test')
            ->where('user_type', User::ROLE_ADMIN_ABSENSI)->first());

        $klien = User::query()->where('email', 'klien@pendekar1721.test')->first();

        $this->assertNotNull($klien);
        $this->assertSame(User::ROLE_CLIENT, $klien->user_type);
        $this->assertTrue(Hash::check('Klien#1721', $klien->password));
    }
}
