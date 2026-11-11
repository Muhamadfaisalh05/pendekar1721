<?php

declare(strict_types=1);

/**
 * Ekspor state awal database oracle (hasil import db/semarak.sql) menjadi
 * fixture JSON yang dipakai untuk menyiapkan database uji parity.
 *
 * Pemakaian: php tests/Support/Absensi/export-seed.php
 */

require_once __DIR__.'/oracle-database.php';

$seedDir = dirname(__DIR__, 2).'/Fixtures/Absensi/seed';

if (! is_dir($seedDir) && ! mkdir($seedDir, 0777, true) && ! is_dir($seedDir)) {
    fwrite(STDERR, "Tidak bisa membuat folder: {$seedDir}\n");
    exit(1);
}

echo "Reset database oracle dari dump...\n";
resetOracleDatabase();

$pdo = oraclePdo();

$anak = $pdo->query('SELECT id, nama, jenis_kelamin, asal_daerah, agama, umur, angkatan, status
    FROM anak ORDER BY id')->fetchAll();

$kegiatan = $pdo->query('SELECT id, nama FROM kegiatan ORDER BY id')->fetchAll();

$evaluasi = $pdo->query('SELECT id, anak_id, kegiatan_id, tanggal, nilai
    FROM evaluasi ORDER BY id')->fetchAll();

$state = [
    'source' => 'semarak/db/semarak.sql',
    'generated_at' => date('c'),
    'counts' => [
        'anak' => count($anak),
        'kegiatan' => count($kegiatan),
        'evaluasi' => count($evaluasi),
    ],
    'anak' => $anak,
    'kegiatan' => $kegiatan,
    'evaluasi' => $evaluasi,
];

file_put_contents(
    $seedDir.'/state.json',
    json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
);

printf(
    "state.json ditulis: %d anak, %d kegiatan, %d evaluasi -> %s\n",
    count($anak),
    count($kegiatan),
    count($evaluasi),
    $seedDir.'/state.json'
);
