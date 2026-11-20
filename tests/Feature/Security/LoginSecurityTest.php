<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * SEC-LOGIN — penguatan keamanan halaman & proses login.
 *
 * Mencakup: anti-cache halaman login, header keamanan (CSP subset),
 * normalisasi email, audit log tanpa kata sandi, rate limit berlapis,
 * regenerasi ID sesi (anti session fixation), dan rehash kata sandi lama.
 */
class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Rahasia#12345';

    private function makeUser(string $email = 'admin.aman@example.test'): User
    {
        return User::query()->create([
            'name' => 'Admin Aman',
            'email' => $email,
            'password' => self::PASSWORD,
            'user_type' => User::ROLE_ADMIN,
        ]);
    }

    public function test_halaman_login_tidak_disimpan_di_cache_browser_maupun_proxy(): void
    {
        // Symfony mengurutkan ulang direktif Cache-Control; nilainya tetap sama.
        $this->get('/login')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
            ->assertHeader('Pragma', 'no-cache');
    }

    public function test_halaman_login_mengirim_csp_subset_dan_noindex(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader(
                'Content-Security-Policy',
                "frame-ancestors 'self'; object-src 'none'; base-uri 'self'; font-src 'self'; form-action 'self'"
            )
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_login_menerima_email_tanpa_membedakan_huruf_besar_kecil(): void
    {
        $this->makeUser('admin.aman@example.test');

        $this->post('/login', [
            'email' => 'ADMIN.AMAN@Example.Test',
            'password' => self::PASSWORD,
        ])->assertRedirect('/admin');

        $this->assertAuthenticated();
    }

    public function test_email_tidak_dikenal_dan_kata_sandi_salah_memberi_pesan_sama(): void
    {
        $this->makeUser();

        $pesan = 'Email atau kata sandi tidak sesuai. Mohon periksa kembali lalu coba lagi.';

        $this->from('/login')
            ->post('/login', ['email' => 'tidak.ada@example.test', 'password' => self::PASSWORD])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => $pesan]);

        $this->from('/login')
            ->post('/login', ['email' => 'admin.aman@example.test', 'password' => 'SandiSalah#1'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => $pesan]);

        $this->assertGuest();
    }

    public function test_percobaan_login_gagal_dicatat_tanpa_kata_sandi(): void
    {
        Log::spy();

        $this->from('/login')->post('/login', [
            'email' => 'tidak.ada@example.test',
            'password' => 'SandiRahasia#1',
        ])->assertRedirect('/login');

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Percobaan login gagal'
                && $context['email'] === 'tidak.ada@example.test'
                && ! array_key_exists('password', $context)
                && ! in_array('SandiRahasia#1', $context, true))
            ->atLeast()->once();
    }

    public function test_rate_limit_mengunci_setelah_lima_percobaan_per_email(): void
    {
        $this->makeUser();

        for ($i = 1; $i <= 5; $i++) {
            $this->from('/login')->post('/login', [
                'email' => 'admin.aman@example.test',
                'password' => 'salah-'.$i,
            ])->assertRedirect('/login');
        }

        $this->from('/login')->post('/login', [
            'email' => 'admin.aman@example.test',
            'password' => 'salah-terakhir',
        ])->assertStatus(429);

        $this->assertGuest();
    }

    public function test_id_sesi_berubah_setelah_login_untuk_mencegah_session_fixation(): void
    {
        $this->makeUser();

        $this->get('/login');
        $idSebelum = session()->getId();

        $this->post('/login', [
            'email' => 'admin.aman@example.test',
            'password' => self::PASSWORD,
        ])->assertRedirect('/admin');

        $this->assertNotSame($idSebelum, session()->getId());
    }

    public function test_hash_kata_sandi_dengan_parameter_lama_diperbarui_saat_login(): void
    {
        $user = $this->makeUser();

        $user->forceFill([
            'password' => password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]),
        ])->save();

        // phpunit memakai BCRYPT_ROUNDS=4; naikkan sementara agar rehash teruji.
        config(['hashing.bcrypt.rounds' => 12]);
        Hash::forgetDrivers();

        $this->assertTrue(Hash::needsRehash($user->fresh()->password));

        $this->post('/login', [
            'email' => 'admin.aman@example.test',
            'password' => self::PASSWORD,
        ])->assertRedirect('/admin');

        $this->assertFalse(Hash::needsRehash($user->fresh()->password));
    }
}
