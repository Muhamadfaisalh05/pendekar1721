<?php

namespace App\Modules\Absensi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modul absensi hanya untuk admin pendekar (superuser) dan admin absensi.
 * Role lain (klien) ditolak 403, tamu diarahkan ke /login oleh middleware `auth`.
 */
class EnsureAbsensiAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->canAccessAbsensi()) {
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
