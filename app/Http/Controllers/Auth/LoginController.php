<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
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
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        $request->session()->regenerate();

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Akun yang diarsipkan tidak boleh dipakai walau password benar.
        if ($user->status === UserStatus::Archived) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Akun ini telah diarsipkan. Hubungi administrator.',
            ]);
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
