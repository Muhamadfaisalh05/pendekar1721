<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 2 — binaan (anak) yang diimpor lebih dulu belum punya email maupun
 * kata sandi; keduanya baru diisi saat akun diaktifkan (kelulusan).
 *
 * Syarat keamanan yang menyertai migrasi ini (lihat
 * docs/BACKUP-LOGIC-SEBELUM-REFACTOR.md §11 butir 4):
 * - `LoginController` menolak akun dengan `password` NULL/kosong memakai pesan
 *   gagal yang sama seperti kredensial salah (tidak membocorkan status akun).
 * - `users.email` tetap UNIQUE; banyak nilai NULL diizinkan sehingga beberapa
 *   binaan tanpa email bisa hidup berdampingan.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Mengembalikan NOT NULL mengharuskan nilai NULL dibereskan lebih dulu.
     * Dipakai nilai pengganti yang tidak mungkin dipakai login:
     * - email  : `binaan-{id}@invalid.local` (domain .local tidak bisa dikirimi surat)
     * - password: string kosong (ditolak guard login, lihat LoginController)
     */
    public function down(): void
    {
        foreach (DB::table('users')->whereNull('email')->pluck('id') as $id) {
            DB::table('users')->where('id', $id)->update(['email' => 'binaan-'.$id.'@invalid.local']);
        }

        DB::table('users')->whereNull('password')->update(['password' => '']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
