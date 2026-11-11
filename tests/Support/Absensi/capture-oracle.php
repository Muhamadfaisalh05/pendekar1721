<?php

declare(strict_types=1);

/**
 * Capture oracle respons API SEMARAK (aplikasi native) untuk uji karakterisasi.
 *
 * Pemakaian:
 *   1. Salin folder semarak ke luar repo (mis. c:\laragon\www\Dinsos\_oracle\semarak)
 *      dan arahkan DB_NAME di includes/db.php ke `semarak_oracle`.
 *   2. php -S 127.0.0.1:8123 -t c:\laragon\www\Dinsos\_oracle\semarak
 *   3. php tests/Support/Absensi/capture-oracle.php http://127.0.0.1:8123
 *
 * Skrip ini me-reset database `semarak_oracle` (drop + import dump) agar fixture
 * deterministik, lalu memanggil setiap action dan menyimpan request/response.
 * Folder sumber `semarak` TIDAK disentuh.
 */

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8123', '/');
$projectRoot = dirname(__DIR__, 3);
$fixtureDir = $projectRoot.'/tests/Fixtures/Absensi/oracle';

require_once __DIR__.'/oracle-database.php';

if (! is_dir($fixtureDir) && ! mkdir($fixtureDir, 0777, true) && ! is_dir($fixtureDir)) {
    fwrite(STDERR, "Tidak bisa membuat folder fixture: {$fixtureDir}\n");
    exit(1);
}

/**
 * Kirim request HTTP dan kembalikan status, header penting, dan body mentah.
 *
 * @return array{status:int,headers:array<string,string|null>,body:string}
 */
function http(string $method, string $url, ?array $payload = null): array
{
    $header = "Accept: application/json\r\n";
    $options = [
        'http' => [
            'method' => $method,
            'ignore_errors' => true,
            'timeout' => 20,
        ],
    ];

    if ($payload !== null) {
        $header .= "Content-Type: application/json\r\n";
        $options['http']['content'] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    $options['http']['header'] = $header;
    $body = @file_get_contents($url, false, stream_context_create($options));

    $status = 0;
    $headers = [];
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches)) {
            $status = (int) $matches[1];
            continue;
        }
        if (str_contains($line, ':')) {
            [$key, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($key))] = trim($value);
        }
    }

    return [
        'status' => $status,
        'headers' => [
            'content-type' => $headers['content-type'] ?? null,
            'x-powered-by' => $headers['x-powered-by'] ?? null,
        ],
        'body' => $body === false ? '' : $body,
    ];
}

$anakId = 'AN-001';

$scenarios = [
    // ---------- GET (baca) ----------
    ['get-anak', 'GET', '/api.php?action=anak'],
    ['get-anak-list', 'GET', '/api.php?action=anak_list'],
    ['get-anak-detail', 'GET', '/api.php?action=anak_detail&id='.$anakId],
    ['get-anak-detail-tidak-ada', 'GET', '/api.php?action=anak_detail&id=AN-999'],
    ['get-kegiatan', 'GET', '/api.php?action=kegiatan'],
    ['get-evaluasi-tanggal', 'GET', '/api.php?action=evaluasi&tanggal=2026-09-24'],
    ['get-evaluasi-tanggal-invalid', 'GET', '/api.php?action=evaluasi&tanggal=2026-13-01'],
    ['get-settings', 'GET', '/api.php?action=settings'],
    ['get-summary-default-id', 'GET', '/api.php?action=summary'],
    ['get-summary-rentang', 'GET', '/api.php?action=summary&id='.$anakId.'&mulai=2026-09-01&sampai=2026-09-30'],
    ['get-summary-anak-tidak-ada', 'GET', '/api.php?action=summary&id=AN-999'],
    ['get-report-default', 'GET', '/api.php?action=report&id='.$anakId],
    ['get-report-rentang', 'GET', '/api.php?action=report&id='.$anakId.'&mulai=2026-09-01&sampai=2026-09-30'],
    ['get-report-anak-tidak-ada', 'GET', '/api.php?action=report&id=AN-999'],
    ['get-dashboard', 'GET', '/api.php?action=dashboard'],
    ['get-dashboard-filter-angkatan', 'GET', '/api.php?action=dashboard&angkatan=Angkatan%20II'],
    ['get-dashboard-filter-rentang', 'GET', '/api.php?action=dashboard&mulai=2026-09-24&sampai=2026-09-24'],
    ['get-dashboard-filter-status', 'GET', '/api.php?action=dashboard&status_anak=Aktif'],
    ['get-dashboard-rentang-terbalik', 'GET', '/api.php?action=dashboard&mulai=2026-09-30&sampai=2026-09-01'],
    ['get-action-tidak-dikenal', 'GET', '/api.php?action=tidak_ada'],

    // ---------- POST (tulis) ----------
    ['post-save-evaluasi', 'POST', '/api.php?action=save_evaluasi', [
        'anak_id' => $anakId, 'kegiatan_id' => 1, 'tanggal' => '2026-09-24', 'nilai' => 'sesuai',
    ]],
    ['post-save-evaluasi-nilai-invalid', 'POST', '/api.php?action=save_evaluasi', [
        'anak_id' => $anakId, 'kegiatan_id' => 1, 'tanggal' => '2026-09-24', 'nilai' => 'hebat',
    ]],
    ['post-save-evaluasi-anak-tidak-ada', 'POST', '/api.php?action=save_evaluasi', [
        'anak_id' => 'AN-999', 'kegiatan_id' => 1, 'tanggal' => '2026-09-24', 'nilai' => 'sesuai',
    ]],
    ['post-save-evaluasi-kegiatan-tidak-ada', 'POST', '/api.php?action=save_evaluasi', [
        'anak_id' => $anakId, 'kegiatan_id' => 999, 'tanggal' => '2026-09-24', 'nilai' => 'sesuai',
    ]],
    ['post-batch', 'POST', '/api.php?action=save_evaluasi_batch', ['items' => [
        ['anak_id' => $anakId, 'kegiatan_id' => 2, 'tanggal' => '2026-09-24', 'nilai' => 'arahan'],
        ['anak_id' => $anakId, 'kegiatan_id' => 3, 'tanggal' => '2026-09-24', 'nilai' => 'telat'],
    ]]],
    ['post-batch-kosong', 'POST', '/api.php?action=save_evaluasi_batch', ['items' => []]],
    ['get-evaluasi-setelah-batch', 'GET', '/api.php?action=evaluasi&tanggal=2026-09-24'],
    ['post-batch-sebagian-gagal', 'POST', '/api.php?action=save_evaluasi_batch', ['items' => [
        ['anak_id' => $anakId, 'kegiatan_id' => 4, 'tanggal' => '2026-09-24', 'nilai' => 'tidak'],
        ['anak_id' => 'AN-999', 'kegiatan_id' => 5, 'tanggal' => '2026-09-24', 'nilai' => 'sesuai'],
    ]]],
    ['get-evaluasi-setelah-batch-gagal', 'GET', '/api.php?action=evaluasi&tanggal=2026-09-24'],

    ['post-anak-store', 'POST', '/api.php?action=anak_store', [
        'id' => 'AN-007', 'nama' => 'Uji Karakterisasi', 'jenis_kelamin' => 'Perempuan',
        'asal_daerah' => 'Bandung', 'agama' => 'Islam', 'umur' => 16, 'angkatan' => 'Angkatan III',
    ]],
    ['post-anak-store-duplikat', 'POST', '/api.php?action=anak_store', [
        'id' => 'AN-007', 'nama' => 'Duplikat', 'jenis_kelamin' => 'Laki-laki',
        'asal_daerah' => 'Bogor', 'agama' => 'Islam', 'umur' => 17, 'angkatan' => 'Angkatan III',
    ]],
    ['post-anak-store-nama-kosong', 'POST', '/api.php?action=anak_store', [
        'nama' => '   ', 'jenis_kelamin' => 'Laki-laki', 'asal_daerah' => 'Bogor',
        'agama' => 'Islam', 'umur' => 17, 'angkatan' => 'Angkatan III',
    ]],
    ['post-anak-update', 'POST', '/api.php?action=anak_update', [
        'id' => 'AN-007', 'nama' => 'Uji Karakterisasi Diubah', 'jenis_kelamin' => 'Perempuan',
        'asal_daerah' => 'Sumedang', 'agama' => 'Islam', 'umur' => 17,
        'angkatan' => 'Angkatan III', 'status' => 'Aktif',
    ]],
    ['post-anak-update-tidak-ada', 'POST', '/api.php?action=anak_update', [
        'id' => 'AN-999', 'nama' => 'Tidak Ada', 'jenis_kelamin' => 'Laki-laki',
        'asal_daerah' => 'Bogor', 'agama' => 'Islam', 'umur' => 17, 'angkatan' => 'Angkatan III',
    ]],
    ['post-anak-delete', 'POST', '/api.php?action=anak_delete&id=AN-007'],
    ['post-anak-delete-tidak-ada', 'POST', '/api.php?action=anak_delete&id=AN-999'],

    ['post-settings-save', 'POST', '/api.php?action=settings_save', [
        'jadwal' => [
            '1' => ['aturan' => 'hari_tertentu', 'hari' => ['1', '3'], 'rentang' => []],
            '2' => ['aturan' => 'multi_rentang', 'hari' => [], 'rentang' => [
                ['mulai' => '2026-09-01', 'selesai' => '2026-09-30'],
            ]],
        ],
        'angkatan' => ['Angkatan II' => '2026-12-31'],
    ]],
    ['post-settings-save-aturan-invalid', 'POST', '/api.php?action=settings_save', [
        'jadwal' => ['1' => ['aturan' => 'kadang_kadang', 'hari' => [], 'rentang' => []]],
    ]],
    ['post-settings-save-hari-invalid', 'POST', '/api.php?action=settings_save', [
        'jadwal' => ['1' => ['aturan' => 'hari_tertentu', 'hari' => ['8'], 'rentang' => []]],
    ]],
    ['post-settings-save-rentang-terbalik', 'POST', '/api.php?action=settings_save', [
        'jadwal' => ['1' => ['aturan' => 'multi_rentang', 'hari' => [], 'rentang' => [
            ['mulai' => '2026-09-30', 'selesai' => '2026-09-01'],
        ]]],
    ]],
    ['get-settings-setelah-simpan', 'GET', '/api.php?action=settings'],

    ['post-report-notes-save', 'POST', '/api.php?action=report_notes_save', [
        'anak_id' => $anakId, 'periode_mulai' => '2026-09-01', 'periode_selesai' => '2026-09-30',
        'catatan' => 'Catatan uji karakterisasi', 'rekomendasi' => 'Rekomendasi uji karakterisasi',
    ]],
    ['post-report-notes-save-periode-invalid', 'POST', '/api.php?action=report_notes_save', [
        'anak_id' => $anakId, 'periode_mulai' => '2026-09-30', 'periode_selesai' => '2026-09-01',
        'catatan' => 'x', 'rekomendasi' => 'y',
    ]],
    ['get-report-setelah-catatan', 'GET', '/api.php?action=report&id='.$anakId.'&mulai=2026-09-01&sampai=2026-09-30'],

    // ---------- Perilaku method ----------
    ['post-ke-action-get', 'POST', '/api.php?action=dashboard', []],
    ['get-ke-action-post', 'GET', '/api.php?action=save_evaluasi'],
];

echo "=== Reset database oracle ===\n";
resetOracleDatabase();
echo "selesai\n\n";

echo "=== Capture {$baseUrl} ===\n";

$manifest = [];
$number = 0;

foreach ($scenarios as $scenario) {
    $number++;
    $name = $scenario[0];
    $method = $scenario[1];
    $uri = $scenario[2];
    $payload = $scenario[3] ?? null;

    $file = sprintf('%02d-%s.json', $number, $name);
    $response = http($method, $baseUrl.$uri, $payload);

    $fixture = [
        'name' => $name,
        'request' => [
            'method' => $method,
            'uri' => $uri,
            'payload' => $payload,
        ],
        'response' => [
            'status' => $response['status'],
            'headers' => $response['headers'],
            'body' => $response['body'],
            'json' => json_decode($response['body'], true),
        ],
    ];

    file_put_contents(
        $fixtureDir.'/'.$file,
        json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
    );

    $manifest[] = [
        'file' => $file,
        'name' => $name,
        'method' => $method,
        'uri' => $uri,
        'status' => $response['status'],
        'body_sha256' => hash('sha256', $response['body']),
    ];

    printf("%2d. %-44s %s %s\n", $number, $name, $response['status'], strlen($response['body']).' B');
}

file_put_contents(
    $fixtureDir.'/manifest.json',
    json_encode([
        'generated_at' => date('c'),
        'source' => 'semarak native (salinan read-only)',
        'base_url' => $baseUrl,
        'scenarios' => $manifest,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
);

echo "\nTotal skenario: {$number}\nFixture: {$fixtureDir}\n";
