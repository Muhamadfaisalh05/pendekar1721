<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan detail deskripsi dan foto untuk katalog Pelatihan/Keterampilan.
     */
    public function up(): void
    {
        Schema::table('master_trainings', function (Blueprint $table) {
            $table->text('description_1')->nullable();
            $table->text('description_2')->nullable();
            $table->string('image_path')->nullable();
        });

        Schema::table('master_skills', function (Blueprint $table) {
            $table->text('description_1')->nullable();
            $table->text('description_2')->nullable();
            $table->string('image_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_trainings', function (Blueprint $table) {
            $table->dropColumn(['description_1', 'description_2', 'image_path']);
        });

        Schema::table('master_skills', function (Blueprint $table) {
            $table->dropColumn(['description_1', 'description_2', 'image_path']);
        });
    }
};
