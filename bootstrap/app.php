<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->prefix('absensi')
                ->name('absensi.')
                ->group(base_path('routes/absensi.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        /*
         * SECURITY: trusted proxy TIDAK diaktifkan di sini karena config belum
         * tersedia pada tahap bootstrap ini. Pengaturan dipindah ke
         * AppServiceProvider::register() dan hanya aktif bila env
         * TRUSTED_PROXIES diisi (lihat config/app.php).
         */

        $middleware->append(SecurityHeaders::class);

        // Putus sesi otomatis bila kata sandi akun berubah (anti session hijack).
        $middleware->authenticateSessions();

        // Semua tamu (termasuk modul absensi) diarahkan ke satu halaman login.
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

