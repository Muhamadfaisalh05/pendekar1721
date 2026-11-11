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
        // Ganti '*' dengan IP/CIDR reverse proxy yang sebenarnya di produksi
        // (lihat docs/SECURITY-AUDIT-L13.md) agar X-Forwarded-For tidak bisa dipalsukan.
        $middleware->trustProxies(at: '*');

        $middleware->append(SecurityHeaders::class);

        // Modul absensi memakai login panel admin (satu pintu masuk untuk semua).
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

