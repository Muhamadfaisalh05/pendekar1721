<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->to($request->user()->dashboardUrl());
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.max' => 'Alamat email terlalu panjang.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak sesuai. Mohon periksa kembali lalu coba lagi.',
            ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();

        // Tolak akun yang tidak berhak masuk ke dashboard mana pun.
        $penolakan = match (true) {
            $user->status === UserStatus::Archived => 'Akun Anda sudah diarsipkan dan tidak dapat digunakan. Silakan hubungi administrator.',
            ! in_array($user->user_type, [
                User::ROLE_ADMIN,
                User::ROLE_ADMIN_ABSENSI,
                User::ROLE_CLIENT,
            ], true) => 'Akun Anda belum memiliki hak akses ke dashboard. Silakan hubungi administrator.',
            default => null,
        };

        if ($penolakan !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages(['email' => $penolakan]);
        }

        return redirect()->to($user->dashboardUrl());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda telah keluar dari aplikasi.');
    }
}
