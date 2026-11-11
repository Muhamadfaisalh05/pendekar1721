<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

/**
 * Middleware auth panel Filament yang mengarahkan tamu ke halaman login
 * terpadu `/login` (bukan `/admin/login` atau `/klien/login`).
 */
class AuthenticatePanel extends FilamentAuthenticate
{
    protected function redirectTo($request): ?string
    {
        return route('login');
    }
}
