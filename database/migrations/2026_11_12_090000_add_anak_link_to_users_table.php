<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 2 — jembatan `users` <-> `semarak.anak`.
 *
 * `anak_id` menautkan satu akun klien ke satu baris `anak` di database SEMARAK
 * sehingga kelulusan binaan bisa mengaktifkan akun yang sama (bukan membuat
 * duplikat). `graduated_at` adalah satu-satunya penanda resmi "alumni":
 *
 *     alumni = user_type = 'client' AND status = 'published'
 *              AND graduated_at IS NOT NULL
 *
 * Catatan penting:
 * - Tidak ada foreign key ke `anak`: tabel itu berada di koneksi/database
 *   terpisah (`semarak`), sehingga integritasnya dijaga di level aplikasi.
 * - `anak_id` nullable + unique: banyak akun lama boleh bernilai NULL, tetapi
 *   satu anak hanya boleh tertaut ke satu akun.
 * - Backfill `graduated_at` untuk klien lama SENGAJA BELUM dilakukan di sini.
 *   Backfill menunggu hasil audit produksi (lihat docs/AUDIT-PRODUKSI-USERS.md).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('anak_id', 20)->nullable()->after('created_by');
            $table->timestamp('graduated_at')->nullable()->after('anak_id');

            // Satu anak = satu akun. NULL berulang tetap diizinkan oleh
            // SQLite/MySQL, jadi akun lama tanpa tautan tidak saling bentrok.
            $table->unique('anak_id');
            $table->index('graduated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['anak_id']);
            $table->dropIndex(['graduated_at']);
            $table->dropColumn(['anak_id', 'graduated_at']);
        });
    }
};
