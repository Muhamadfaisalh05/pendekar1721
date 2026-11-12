<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Satu pintu login untuk semua role.
 *
 * Setelah autentikasi, pengguna diarahkan ke dashboard sesuai `user_type`:
 *   admin          -> /admin      (panel Filament admin)
 *   admin_absensi  -> /absensi    (modul SEMARAK)
 *   client         -> /klien      (panel Filament klien)
 */
class LoginController extends Controller
{
    /**
     * Hash bcrypt dummy. Dipakai saat email tidak ditemukan agar proses
     * verifikasi tetap berjalan (waktu respons serupa) sehingga penyerang
     * tidak dapat menebak email mana yang terdaftar lewat timing attack.
     */
    private const TIMING_SAFE_HASH = '$2y$12$2ySAOLFXZbfN64qDy/wm.upIsaCVzbsNSsR.EbaKors9N.IWA0B3q';

    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->to($request->user()->dashboardUrl());
        }

        // Halaman login memuat data sesi/CSRF: jangan pernah di-cache browser
        // maupun proxy agar tidak bisa dibuka ulang dari cache setelah logout.
        return response()
            ->view('auth.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private')
            ->header('Pragma', 'no-cache');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.max' => 'Alamat email terlalu panjang.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.max' => 'Kata sandi terlalu panjang.',
        ]);

        $email = Str::lower(trim((string) $validated['email']));
        $password = (string) $validated['password'];
        $remember = $request->boolean('remember');

        $user = User::query()->where('email', $email)->first();

        if (! $this->kredensialValid($password, $user)) {
            $this->catatPercobaanGagal($request, $email);

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak sesuai. Mohon periksa kembali lalu coba lagi.',
            ]);
        }

        /** @var User $user */
        // Tolak akun yang tidak berhak masuk ke dashboard mana pun.
        // `Draft` = akun sudah dibuat tetapi belum diaktifkan (mis. binaan
        // hasil import), sehingga belum boleh login sama sekali.
        $penolakan = match (true) {
            $user->status === UserStatus::Draft => 'Akun Anda belum diaktifkan. Silakan hubungi administrator Pendekar 1721.',
            $user->status === UserStatus::Archived => 'Akun Anda sudah diarsipkan dan tidak dapat digunakan. Silakan hubungi administrator.',
            ! in_array($user->user_type, [
                User::ROLE_ADMIN,
                User::ROLE_ADMIN_ABSENSI,
                User::ROLE_CLIENT,
            ], true) => 'Akun Anda belum memiliki hak akses ke dashboard. Silakan hubungi administrator.',
            default => null,
        };

        if ($penolakan !== null) {
            $this->catatPercobaanGagal($request, $email, $user->status?->value);

            throw ValidationException::withMessages(['email' => $penolakan]);
        }

        // Perbarui hash bila parameter hashing sudah usang (dilakukan sebelum
        // sesi dibuat agar hash di sesi selalu sama dengan hash di database).
        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => Hash::make($password)])->saveQuietly();
        }

        Auth::guard('web')->login($user, $remember);

        // Cegah session fixation: ID sesi baru setelah hak akses berubah.
        $request->session()->regenerate();

        return redirect()->to($user->dashboardUrl());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda telah keluar dari aplikasi.');
    }

    /**
     * Verifikasi kata sandi dengan waktu respons yang relatif konstan.
     * Hash tetap diperiksa walau email tidak ditemukan (dummy hash), dan
     * hash yang tidak dikenali (mis. data lama) dianggap tidak cocok.
     *
     * Akun tanpa kata sandi — binaan yang belum diaktifkan, lihat migrasi
     * `make_users_email_and_password_nullable` — selalu gagal di sini. Hash
     * dummy tetap dijalankan agar waktu respons tidak membocorkan bahwa akun
     * tersebut memang belum punya kata sandi.
     */
    private function kredensialValid(string $password, ?User $user): bool
    {
        if ($user === null || blank($user->password)) {
            Hash::check($password, self::TIMING_SAFE_HASH);

            return false;
        }

        try {
            $cocok = Hash::check($password, $user->password);
        } catch (RuntimeException) {
            $cocok = false;
        }

        return $cocok;
    }

    /**
     * Catat percobaan login yang gagal untuk audit keamanan.
     * Kata sandi tidak pernah dicatat.
     */
    private function catatPercobaanGagal(Request $request, string $email, ?string $status = null): void
    {
        Log::warning('Percobaan login gagal', [
            'email' => $email,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 200),
            'status_akun' => $status,
        ]);
    }
}
