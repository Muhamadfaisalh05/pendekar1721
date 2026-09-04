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

        // Paginator::defaultView('pagination::default');
        //
        // Paginator::defaultSimpleView('pagination::simple-default');
    }
}
