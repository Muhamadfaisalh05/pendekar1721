<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `jadwal_kegiatan` — parity dengan aplikasi native SEMARAK.
 * Satu baris per kegiatan; `hari_json` & `rentang_json` menyimpan konfigurasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('semarak')->hasTable('jadwal_kegiatan')) {
            return;
        }

        Schema::connection('semarak')->create('jadwal_kegiatan', function (Blueprint $table) {
            $table->integer('kegiatan_id')->primary();
            $table->enum('aturan', ['setiap_hari', 'hari_kerja', 'hari_tertentu', 'multi_rentang'])
                ->default('setiap_hari');
            $table->text('hari_json')->nullable();
            $table->text('rentang_json')->nullable();

            $table->foreign('kegiatan_id')->references('id')->on('kegiatan')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('semarak')->dropIfExists('jadwal_kegiatan');
    }
};
