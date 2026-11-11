<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `masa_pembinaan` — parity dengan aplikasi native SEMARAK.
 * Dipakai untuk menandai angkatan yang sudah selesai (status anak -> Lulus).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('semarak')->hasTable('masa_pembinaan')) {
            return;
        }

        Schema::connection('semarak')->create('masa_pembinaan', function (Blueprint $table) {
            $table->string('angkatan', 50)->primary();
            $table->date('tanggal_selesai')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('semarak')->dropIfExists('masa_pembinaan');
    }
};
