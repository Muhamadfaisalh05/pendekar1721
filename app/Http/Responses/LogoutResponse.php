<?php

namespace App\Http\Responses;

use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Http\RedirectResponse;

/**
 * Semua logout (panel Filament maupun modul absensi) berakhir di `/login`.
 *
 * Redirect bawaan Filament memakai `Filament::getUrl()` yang membangun
 * navigasi panel. Saat sesi sudah di-invalidate, `canAccess()` setiap halaman
 * dievaluasi tanpa user dan dulu memicu error 500 ("Attempt to read property
 * user_type on null"). Redirect langsung ke `/login` menghindari proses itu
 * sekaligus menyamakan pesan dengan LogoutController web.
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        return redirect()->route('login')->with('status', 'Anda telah keluar dari aplikasi.');
    }
}
