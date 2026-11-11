<?php

namespace Tests\Feature\Absensi;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Absensi\AbsensiTestCase;

/**
 * Uji karakterisasi: seluruh skenario oracle native harus menghasilkan
 * status + body yang identik dengan implementasi Laravel.
 */
class AbsensiApiParityTest extends AbsensiTestCase
{
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
            $actualBody = (string) $response->getContent();

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
