<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `kegiatan` — parity dengan aplikasi native SEMARAK.
 * Native: id int AUTO_INCREMENT, nama varchar(255) UNIQUE, tanpa timestamps.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('semarak')->hasTable('kegiatan')) {
            return;
        }

        Schema::connection('semarak')->create('kegiatan', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('nama', 255)->unique();
        });
    }

    public function down(): void
    {
        Schema::connection('semarak')->dropIfExists('kegiatan');
    }
};
