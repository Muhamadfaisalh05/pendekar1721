<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('master_cities', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('master_education_degrees', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('master_skills', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('master_religions', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('master_ethnic_groups', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('master_trainings', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->text('description')->nullable();

            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender')->nullable();

            $table->string('ktp_address')->nullable();
            $table->foreignId('ktp_city_id')->nullable()->constrained('master_cities')->nullOnDelete();

            $table->string('domisili_address')->nullable();
            $table->foreignId('domisili_city_id')->nullable()->constrained('master_cities')->nullOnDelete();

            $table->foreignId('education_degree_id')->nullable()->constrained('master_education_degrees')->nullOnDelete();

            $table->unsignedSmallInteger('body_weight')->nullable();
            $table->unsignedSmallInteger('body_height')->nullable();

            $table->foreignId('religion_id')->nullable()->constrained('master_religions')->nullOnDelete();
            $table->foreignId('ethnic_group_id')->nullable()->constrained('master_ethnic_groups')->nullOnDelete();

            $table->string('profile_picture_path')->nullable();

            $table->string('skck_status')->nullable();
            $table->string('surat_kesehatan_status')->nullable();

            $table->string('hire_status')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('user_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('experience_year')->nullable();
            $table->text('experience_description')->nullable();
            $table->timestamps();
        });

        Schema::create('user_skills', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('master_skill_id')->constrained('master_skills')->cascadeOnDelete();
        });

        Schema::create('user_trainings', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('master_training_id')->constrained('master_trainings')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
