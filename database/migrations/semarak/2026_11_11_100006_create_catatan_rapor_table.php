<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `catatan_rapor` — parity dengan aplikasi native SEMARAK.
 * Catatan & rekomendasi rapor per anak per periode.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('semarak')->hasTable('catatan_rapor')) {
            return;
        }

        Schema::connection('semarak')->create('catatan_rapor', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('anak_id', 20);
            $table->date('periode_mulai');
            $table->date('periode_selesai');
            $table->text('catatan');
            $table->text('rekomendasi');

            $table->unique(['anak_id', 'periode_mulai', 'periode_selesai'], 'unique_rapor');

            $table->foreign('anak_id')->references('id')->on('anak')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('semarak')->dropIfExists('catatan_rapor');
    }
};
