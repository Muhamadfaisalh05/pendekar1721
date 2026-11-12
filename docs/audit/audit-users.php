<?php

/*
|--------------------------------------------------------------------------
| AUDIT READ-ONLY tabel users — PENDEKAR1721
|--------------------------------------------------------------------------
| Skrip ini HANYA menjalankan SELECT/agregasi. Tidak ada INSERT, UPDATE,
| DELETE, atau DDL di dalamnya, sehingga aman dijalankan pada database
| produksi selama koneksi database mengarah ke sana.
|
| Cara menjalankan (lihat docs/AUDIT-PRODUKSI-USERS.md):
|   php artisan tinker --execute="require base_path('docs/audit/audit-users.php');"
|
| Samarkan email saat hasil akan dibagikan:
|   AUDIT_MASK_PII=1 php artisan tinker --execute="require base_path('docs/audit/audit-users.php');"
*/

$mask = (bool) (getenv('AUDIT_MASK_PII') ?: env('AUDIT_MASK_PII', false));

/** Samarkan email (PII) tanpa menghilangkan pola domain & duplikat. */
$sensor = static function (?string $nilai) use ($mask): string {
    if ($nilai === null || $nilai === '') {
        return '(kosong)';
    }

    if (! $mask) {
        return $nilai;
    }

    [$user, $domain] = array_pad(explode('@', $nilai, 2), 2, null);

    $userTersamar = mb_substr($user, 0, 2).str_repeat('*', max(1, mb_strlen($user) - 2));

    return $domain === null ? $userTersamar : $userTersamar.'@'.$domain;
};

$koneksi = (string) config('database.default');

echo str_repeat('=', 70)."\n";
echo "AUDIT READ-ONLY TABEL users — PENDEKAR1721\n";
echo str_repeat('=', 70)."\n";
echo 'waktu      : '.now()->toDateTimeString().' ('.config('app.timezone').")\n";
echo 'APP_ENV    : '.config('app.env')."\n";
echo 'APP_URL    : '.config('app.url')."\n";
echo 'koneksi    : '.$koneksi.' (driver '.config("database.connections.{$koneksi}.driver").")\n";

try {
    echo 'database   : '.DB::connection()->getDatabaseName()."\n";
} catch (Throwable $e) {
    echo 'database   : ERROR '.$e->getMessage()."\n";
}

echo "mode       : SELECT/agregasi saja — tidak ada INSERT/UPDATE/DELETE\n";
echo 'sensor PII : '.($mask ? 'AKTIF' : 'nonaktif (set AUDIT_MASK_PII=1 untuk menyamarkan email)')."\n";

$total = DB::table('users')->count();
echo "\nTOTAL users: {$total}\n\n";

echo "== user_type x status ==\n";
foreach (DB::table('users')->selectRaw('user_type, status, COUNT(*) as n')->groupBy('user_type', 'status')->orderBy('user_type')->orderBy('status')->get() as $r) {
    echo sprintf("  %-10s %-12s %6d\n", $r->user_type ?? '(null)', $r->status ?? '(null)', $r->n);
}

echo "\n== client: created_by ==\n";
foreach (DB::table('users')->where('user_type', 'client')->selectRaw('created_by, COUNT(*) as n')->groupBy('created_by')->orderByDesc('n')->limit(15)->get() as $r) {
    echo sprintf("  created_by=%-8s %6d\n", $r->created_by ?? 'NULL', $r->n);
}

echo "\n== client: tahun created_at (agregasi PHP, driver-agnostic) ==\n";
$perYear = [];
foreach (DB::table('users')->where('user_type', 'client')->pluck('created_at') as $ts) {
    $key = $ts ? substr((string) $ts, 0, 4) : 'NULL';
    $perYear[$key] = ($perYear[$key] ?? 0) + 1;
}
ksort($perYear);
foreach ($perYear as $th => $n) {
    echo sprintf("  %-6s %6d\n", $th, $n);
}

echo "\n== client: bulan created_at (12 terbaru, agregasi PHP) ==\n";
$perMonth = [];
foreach (DB::table('users')->where('user_type', 'client')->pluck('created_at') as $ts) {
    $key = $ts ? substr((string) $ts, 0, 7) : 'NULL';
    $perMonth[$key] = ($perMonth[$key] ?? 0) + 1;
}
krsort($perMonth);
foreach (array_slice($perMonth, 0, 12, true) as $bl => $n) {
    echo sprintf("  %-8s %6d\n", $bl, $n);
}

echo "\n== client: kelengkapan data ==\n";
$noProfile = DB::table('users')->where('users.user_type', 'client')->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')->whereNull('user_profiles.id')->count();
$noTraining = DB::table('users')->where('users.user_type', 'client')->leftJoin('user_trainings', 'user_trainings.user_id', '=', 'users.id')->whereNull('user_trainings.user_id')->count();
$noPhoto = DB::table('users')->where('users.user_type', 'client')->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')->where(function ($q) {
    $q->whereNull('user_profiles.profile_picture_path')->orWhere('user_profiles.profile_picture_path', '');
})->count();
$draft = DB::table('users')->where('user_type', 'client')->where('status', 'draft')->count();
$archived = DB::table('users')->where('user_type', 'client')->where('status', 'archived')->count();
echo "  tanpa userProfile     : {$noProfile}\n";
echo "  tanpa pelatihan       : {$noTraining}\n";
echo "  tanpa foto profil     : {$noPhoto}\n";
echo "  status draft          : {$draft}\n";
echo "  status archived       : {$archived}\n";

echo "\n== client: 15 data terbaru (email disamarkan bila AUDIT_MASK_PII=1) ==\n";
foreach (DB::table('users')->where('user_type', 'client')->orderByDesc('id')->limit(15)->get(['id', 'name', 'email', 'status', 'created_by', 'created_at']) as $r) {
    echo sprintf("  #%-6d %-28s %-34s %-10s cb=%-6s %s\n", $r->id, mb_substr($r->name, 0, 27), $sensor($r->email), $r->status ?? 'NULL', $r->created_by ?? 'NULL', $r->created_at);
}

echo "\n== email duplikat (case-insensitive) ==\n";
foreach (DB::table('users')->whereNotNull('email')->selectRaw('LOWER(email) as e, COUNT(*) as n')->groupBy('e')->havingRaw('COUNT(*) > 1')->get() as $r) {
    echo '  '.$sensor($r->e)." x{$r->n}\n";
}

echo "\n== admin/superuser ==\n";
foreach (DB::table('users')->where('user_type', '!=', 'client')->orWhereNull('user_type')->get(['id', 'name', 'email', 'user_type', 'status']) as $r) {
    echo sprintf("  #%-4d %-24s %-34s type=%-14s status=%s\n", $r->id, mb_substr($r->name, 0, 23), $sensor($r->email), $r->user_type ?? 'NULL', $r->status ?? 'NULL');
}

echo "\n== password NULL / kosong ==\n";
echo '  password NULL  : '.DB::table('users')->whereNull('password')->count()."\n";
echo '  password kosong: '.DB::table('users')->where('password', '')->count()."\n";

echo "\n== kesiapan definisi alumni (client + published + graduated_at) ==\n";
if (Schema::hasColumn('users', 'graduated_at')) {
    $dasar = DB::table('users')->where('user_type', 'client')->where('status', 'published');
    echo '  published + graduated_at terisi : '.(clone $dasar)->whereNotNull('graduated_at')->count()."\n";
    echo '  published + graduated_at NULL   : '.(clone $dasar)->whereNull('graduated_at')->count()."\n";
} else {
    echo "  kolom graduated_at BELUM ADA (migrasi Fase 2 belum dijalankan)\n";
}
if (Schema::hasColumn('users', 'anak_id')) {
    echo '  anak_id terisi                  : '.DB::table('users')->whereNotNull('anak_id')->count()."\n";
    echo '  anak_id NULL                    : '.DB::table('users')->whereNull('anak_id')->count()."\n";
} else {
    echo "  kolom anak_id BELUM ADA (migrasi Fase 2 belum dijalankan)\n";
}

echo "\n== koneksi & driver ==\n";
foreach (array_keys(config('database.connections')) as $conn) {
    try {
        echo sprintf("  %-10s driver=%-8s db=%s\n", $conn, config("database.connections.{$conn}.driver"), DB::connection($conn)->getDatabaseName());
    } catch (Throwable $e) {
        echo sprintf("  %-10s ERROR %s\n", $conn, $e->getMessage());
    }
}

echo "\n== semarak.anak (read-only) ==\n";
try {
    echo '  total anak: '.DB::connection('semarak')->table('anak')->count()."\n";
    foreach (DB::connection('semarak')->table('anak')->selectRaw('status, COUNT(*) as n')->groupBy('status')->get() as $r) {
        echo sprintf("  status=%-12s %6d\n", $r->status, $r->n);
    }
    echo "  per angkatan:\n";
    foreach (DB::connection('semarak')->table('anak')->selectRaw('angkatan, COUNT(*) as n')->groupBy('angkatan')->orderBy('angkatan')->get() as $r) {
        echo sprintf("    %-16s %6d\n", $r->angkatan, $r->n);
    }
} catch (Throwable $e) {
    echo '  ERROR: '.$e->getMessage()."\n";
}

echo "\n== users: kolom anak_id / graduated_at sudah ada? ==\n";
foreach (['anak_id', 'graduated_at'] as $col) {
    echo sprintf("  %-14s %s\n", $col, Schema::hasColumn('users', $col) ? 'ADA' : 'BELUM ADA');
}

echo "\n[selesai] Tidak ada data yang diubah.\n";
