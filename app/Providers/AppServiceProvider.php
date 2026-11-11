<?php

namespace App\Providers;

use App\Http\Responses\LogoutResponse;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * Tombol Keluar di panel Filament memakai LogoutResponse bawaan yang
         * me-redirect ke URL panel (memicu error 500 karena navigasi panel
         * dibangun setelah sesi berakhir). Semua logout diarahkan ke /login.
         */
        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);

        /*
         * SECURITY: reverse proxy hanya dipercaya bila TRUSTED_PROXIES diisi
         * (dipisah koma, mis. "10.0.0.1,10.0.0.2").
         *
         * Sebelumnya bootstrap/app.php memakai trustProxies(at: '*') sehingga
         * header X-Forwarded-For bisa dipalsukan siapa pun: IP palsu membuat
         * rate limit login/API bisa dilewati dan deteksi HTTPS ikut keliru.
         * Default sekarang: tidak ada proxy yang dipercaya.
         *
         * Nilai config dibaca di sini karena pada bootstrap/app.php config
         * belum dimuat (dan env() tidak terbaca saat config di-cache).
         */
        $trustedProxies = config('app.trusted_proxies');

        if (is_string($trustedProxies) && trim($trustedProxies) !== '') {
            TrustProxies::at(array_values(array_filter(array_map('trim', explode(',', $trustedProxies)))));
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $forceRootUrl = config('app.force_root_url');
        $forceScheme = config('app.force_scheme');

        if ($forceRootUrl !== null) {
            URL::forceRootUrl($forceRootUrl);
        }

        if ($forceScheme !== null) {
            URL::forceScheme($forceScheme);
        }

        /*
         * SECURITY (FASE 2): Model::unguard() global DIHAPUS.
         * Setiap model kini memakai $fillable eksplisit agar atribut
         * sensitif (user_type, status, created_by, user_id relasi)
         * tidak bisa diubah lewat mass assignment dari input pengguna.
         */

        /*
         * Sebelumnya dua baris ini di-set oleh
         * LaravelFrontendPresets\Tall\TallServiceProvider::boot().
         * Paket laravel-frontend-presets/tall tidak kompatibel dengan
         * Laravel 12+, sehingga setting pagination dipindahkan ke sini agar
         * view resources/views/vendor/pagination/{bootstrap-3,simple-bootstrap-3}.blade.php
         * tetap dipakai dan tampilan pagination tidak berubah.
         *
         * Laravel 13 mengganti nama view bawaan Bootstrap 3:
         *   pagination::default         -> pagination::bootstrap-3
         *   pagination::simple-default  -> pagination::simple-bootstrap-3
         */
        Paginator::defaultView('pagination::bootstrap-3');
        Paginator::defaultSimpleView('pagination::simple-bootstrap-3');

        /*
         * SECURITY: rate limit login (mitigasi brute force & credential
         * stuffing). Tiga lapis:
         *   1. per email + IP  -> 5 percobaan/menit
         *   2. per email       -> 10 percobaan/menit (tetap berlaku walau IP
         *                         penyerang berubah, mis. lewat proxy/VPN)
         *   3. per IP          -> 20 percobaan/menit (proteksi sumber daya)
         * Setelah batas terlampaui Laravel membalas HTTP 429 + Retry-After.
         */
        RateLimiter::for('login', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));
            $emailKey = $email === '' ? 'kosong' : Str::limit(Str::transliterate($email), 120, '');

            return [
                Limit::perMinute(5)->by('login:email-ip:'.$emailKey.'|'.$request->ip()),
                Limit::perMinute(10)->by('login:email:'.$emailKey),
                Limit::perMinute(20)->by('login:ip:'.$request->ip()),
            ];
        });

        /*
         * Rate limit modul absensi: pembacaan cukup longgar, penulisan dibatasi
         * lebih ketat karena mengubah data evaluasi/anak.
         */
        RateLimiter::for('absensi-api', function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        RateLimiter::for('absensi-writes', function (Request $request): Limit {
            $key = ($request->user()?->getAuthIdentifier() ?? $request->ip()).'|absensi-write';

            return Limit::perMinute(30)->by($key);
        });
    }
}
