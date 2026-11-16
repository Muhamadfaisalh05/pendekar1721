<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fallback untuk file pada disk `public`.
 *
 * Idealnya `public/storage` adalah symlink hasil `php artisan storage:link`.
 * Di lingkungan tertentu (mis. Windows/Laragon) link tersebut bisa belum ada
 * sehingga `/storage/...` menghasilkan 404 dan foto profil tampil rusak.
 * Route ini melayani file yang sama langsung dari disk `public` tanpa
 * mengubah URL yang sudah dihasilkan `Storage::disk('public')->url()`.
 */
class PublicStorageController extends Controller
{
    /**
     * MIME gambar yang boleh ditampilkan. SVG sengaja tidak diizinkan karena
     * dapat memuat skrip.
     *
     * @var array<int, string>
     */
    private const ALLOWED_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    public function __invoke(string $path): StreamedResponse
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        // Tolak path kosong dan percobaan keluar dari direktori disk.
        abort_if($path === '' || str_contains($path, '..'), 404);

        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        $mimeType = (string) $disk->mimeType($path);

        abort_unless(in_array($mimeType, self::ALLOWED_IMAGE_MIME_TYPES, true), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
