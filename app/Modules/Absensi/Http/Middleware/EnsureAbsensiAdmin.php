<?php

namespace App\Modules\Absensi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modul absensi hanya untuk pengguna ber-role admin (memakai kolom `user_type`
 * yang sudah dipakai panel Filament pada aplikasi ini).
 */
class EnsureAbsensiAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ($user->user_type ?? null) !== 'admin') {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses modul absensi hanya untuk admin.',
                ], 403);
            }

            abort(403, 'Akses modul absensi hanya untuk admin.');
        }

        return $next($request);
    }
}
