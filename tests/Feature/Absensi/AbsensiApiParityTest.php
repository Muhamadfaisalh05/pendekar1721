<?php

namespace Tests\Feature\Absensi;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Absensi\AbsensiTestCase;

/**
 * Uji karakterisasi: seluruh skenario oracle native harus menghasilkan
 * status + body yang identik dengan implementasi Laravel.
 *
 * DATE DRIFT: modul absensi sengaja memakai `date('Y-m-d')` (bukan `now()`)
 * agar perilakunya sama persis dengan aplikasi native SEMARAK. Karena itu
 * `Carbon::setTestNow()` TIDAK membekukan tanggal default di modul ini.
 * Fixture oracle direkam pada 2026-11-11 (`manifest.json` → `generated_at`),
 * jadi tanggal hari ini dinormalkan ke tanggal rekaman sebelum dibandingkan.
 * Perbandingan lain (status, struktur, angka, urutan) tetap ketat.
 */
class AbsensiApiParityTest extends AbsensiTestCase
{
    /** Tanggal saat fixture oracle direkam (periode default = hari itu). */
    private const TANGGAL_FIXTURE = '2026-11-11';

    public function test_seluruh_skenario_oracle_identik(): void
    {
        $fixtureDir = base_path('tests/Fixtures/Absensi/oracle');
        $manifest = json_decode((string) file_get_contents($fixtureDir.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->actingAs($this->adminUser());

        $failures = [];

        foreach ($manifest['scenarios'] as $scenario) {
            $fixture = json_decode(
                (string) file_get_contents($fixtureDir.'/'.$scenario['file']),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $request = $fixture['request'];
            $content = $request['payload'] !== null
                ? json_encode($request['payload'], JSON_UNESCAPED_UNICODE)
                : null;

            $response = $this->call(
                $request['method'],
                $this->mapUri($request['uri']),
                [],
                [],
                [],
                ['HTTP_ACCEPT' => 'application/json'],
                $content,
            );

            $expectedStatus = (int) $fixture['response']['status'];
            $expectedBody = (string) $fixture['response']['body'];
            $actualBody = $this->normalkanTanggal((string) $response->getContent());

            if ($response->getStatusCode() !== $expectedStatus || $actualBody !== $expectedBody) {
                $failures[] = sprintf(
                    "%s\n  status : oracle=%d laravel=%d\n  oracle : %s\n  laravel: %s",
                    $scenario['name'],
                    $expectedStatus,
                    $response->getStatusCode(),
                    $expectedBody,
                    $actualBody
                );
            }
        }

        $this->assertSame([], $failures, implode("\n\n", $failures));
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/absensi/api?action=anak')->assertRedirect();
    }

    public function test_pengguna_non_admin_ditolak(): void
    {
        $client = User::query()->create([
            'name' => 'Klien Uji',
            'email' => 'klien-absensi@example.test',
            'password' => Hash::make('password'),
            'user_type' => 'client',
        ]);

        $this->actingAs($client)
            ->getJson('/absensi/api?action=anak')
            ->assertForbidden();
    }

    /**
     * Ganti tanggal hari ini dengan tanggal rekaman fixture agar periode
     * default ("hari ini") pada respons bisa dibandingkan dengan oracle.
     */
    private function normalkanTanggal(string $body): string
    {
        return str_replace(date('Y-m-d'), self::TANGGAL_FIXTURE, $body);
    }

    private function mapUri(string $uri): string
    {
        return str_replace('/api.php', '/absensi/api', $uri);
    }

    private function adminUser(): User
    {
        return User::query()->create([
            'name' => 'Admin Absensi',
            'email' => 'admin-absensi@example.test',
            'password' => Hash::make('password'),
            'user_type' => 'admin',
        ]);
    }
}
