<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `anak` — parity dengan aplikasi native SEMARAK.
 * Tanpa timestamps (native tidak punya kolom created_at/updated_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('semarak')->hasTable('anak')) {
            return;
        }

        Schema::connection('semarak')->create('anak', function (Blueprint $table) {
            $table->string('id', 20)->primary();
            $table->string('nama', 150);
            $table->string('jenis_kelamin', 20);
            $table->string('asal_daerah', 100);
            $table->string('agama', 50);
            $table->integer('umur');
            $table->string('angkatan', 50);
            $table->string('status', 30)->default('Aktif');
        });
    }

    public function down(): void
    {
        Schema::connection('semarak')->dropIfExists('anak');
    }
};
