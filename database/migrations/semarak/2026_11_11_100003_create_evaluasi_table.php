<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `evaluasi` — parity dengan aplikasi native SEMARAK.
 * `libur` adalah bagian dari enum native (dipakai untuk sakit/izin).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('semarak')->hasTable('evaluasi')) {
            return;
        }

        Schema::connection('semarak')->create('evaluasi', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('anak_id', 20);
            $table->integer('kegiatan_id');
            $table->date('tanggal');
            $table->enum('nilai', ['sesuai', 'arahan', 'telat', 'tidak', 'libur']);

            $table->unique(['anak_id', 'kegiatan_id', 'tanggal'], 'unique_eval');

            $table->foreign('anak_id')->references('id')->on('anak')->cascadeOnDelete();
            $table->foreign('kegiatan_id')->references('id')->on('kegiatan')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('semarak')->dropIfExists('evaluasi');
    }
};
