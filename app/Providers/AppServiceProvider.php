<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }
}
