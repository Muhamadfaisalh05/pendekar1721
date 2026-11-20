<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-TEST 03 — Aplikasi tidak boleh memuat aset (khususnya font) dari
 * domain pihak ketiga: font Inter di-self-host di public/fonts/inter dan
 * dideklarasikan lewat resources/sass/_fonts.scss.
 */
class SelfHostedAssetsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Domain CDN font yang tidak boleh muncul lagi di HTML mana pun.
     */
    private const FORBIDDEN_FONT_HOSTS = [
        'rsms.me',
        'fonts.googleapis.com',
        'fonts.gstatic.com',
        'fonts.bunny.net',
        'use.typekit.net',
    ];

    public function test_halaman_tidak_memuat_font_pihak_ketiga(): void
    {
        foreach (['/', '/login'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            foreach (self::FORBIDDEN_FONT_HOSTS as $host) {
                $this->assertStringNotContainsString(
                    $host,
                    $html,
                    "Halaman {$url} masih memuat aset font dari {$host}."
                );
            }
        }
    }

    public function test_berkas_font_inter_tersedia_di_folder_public(): void
    {
        $files = [
            'fonts/inter/InterVariable.woff2',
            'fonts/inter/InterVariable-Italic.woff2',
            'fonts/inter/LICENSE.txt',
        ];

        foreach ($files as $file) {
            $path = public_path($file);

            $this->assertFileExists($path, "Aset font lokal hilang: public/{$file}");
            $this->assertGreaterThan(0, (int) filesize($path), "Aset font kosong: public/{$file}");
        }
    }
}
