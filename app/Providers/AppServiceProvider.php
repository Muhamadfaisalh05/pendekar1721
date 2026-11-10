<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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

        Model::unguard();

        /*
         * Sebelumnya dua baris ini di-set oleh
         * LaravelFrontendPresets\Tall\TallServiceProvider::boot().
         * Paket laravel-frontend-presets/tall tidak kompatibel dengan
         * Laravel 12, sehingga setting pagination dipindahkan ke sini agar
         * view resources/views/vendor/pagination/{default,simple-default}.blade.php
         * tetap dipakai dan tampilan pagination tidak berubah.
         */
        Paginator::defaultView('pagination::default');
        Paginator::defaultSimpleView('pagination::simple-default');
    }
}
