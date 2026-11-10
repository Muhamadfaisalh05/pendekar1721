<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Batasan Alur Import Data
    |--------------------------------------------------------------------------
    |
    | Nilai-nilai ini dipakai oleh App\Services\ImportService dan halaman
    | Import Peserta (admin & klien). Lihat docs/SECURITY-AUDIT-L13.md FASE 3.
    |
    */

    'import' => [

        // Jumlah baris maksimum per file import (mencegah DoS / memory bloat).
        'max_rows' => (int) env('IMPORT_MAX_ROWS', 2000),

        // Hash (bcrypt/argon) dari "kata sandi awal" opsional yang dipakai
        // operator saat import. Tidak pernah menyimpan plaintext di kode.
        // Buat dengan: php artisan tinker --execute="echo Hash::make('...');"
        'password_hash' => env('IMPORT_PASSWORD_HASH'),

        // Panjang password acak untuk peserta yang diimpor tanpa kata sandi awal.
        'password_length' => (int) env('IMPORT_PASSWORD_LENGTH', 32),

    ],

];
