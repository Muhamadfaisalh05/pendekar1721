<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identitas Instansi
    |--------------------------------------------------------------------------
    |
    | Nama instansi yang dicetak pada template import (Excel & Word). Boleh
    | dikosongkan; template akan memakai placeholder "Nama Instansi" supaya
    | tidak pernah menampilkan nama instansi yang salah.
    |
    */

    'instansi_nama' => env('PENDEKAR_INSTANSI_NAMA', ''),

    /*
    |--------------------------------------------------------------------------
    | Batasan Alur Import Data
    |--------------------------------------------------------------------------
    |
    | Nilai-nilai ini dipakai oleh App\Services\ImportService dan halaman
    | Import Data (panel Admin). Lihat docs/SECURITY-AUDIT-L13.md FASE 3.
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

    /*
    |--------------------------------------------------------------------------
    | Direktori Alumni (panel Klien)
    |--------------------------------------------------------------------------
    |
    | Klien hanya melihat ANGKA alumni di dashboard. Daftar alumni (read-only)
    | bersifat opsional dan harus diaktifkan eksplisit. Bila aktif, daftar
    | TIDAK boleh memuat email, telepon, atau alamat (lihat D-02).
    |
    */

    'alumni' => [

        'directory_enabled' => (bool) env('PENDEKAR_ALUMNI_DIRECTORY', false),

    ],

];
