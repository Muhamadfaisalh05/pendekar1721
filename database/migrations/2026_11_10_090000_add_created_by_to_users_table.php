<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 2 (Opsi A) — kepemilikan data peserta.
 *
 * Kolom created_by menyimpan siapa yang mendaftarkan/mengimpor peserta
 * sehingga panel /klien dapat dibatasi hanya pada data milik klien tersebut.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // FK ditambahkan terpisah karena SQLite tidak mendukung
            // penambahan foreign key pada tabel yang sudah ada.
            $table->unsignedBigInteger('created_by')->nullable()->after('user_type');
            $table->index('created_by');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        // Backfill: peserta lama tidak menyimpan pemiliknya. Data tersebut
        // ditetapkan sebagai milik admin pertama (bukan milik klien mana pun)
        // agar tidak ada klien yang tiba-tiba mewarisi data klien lain.
        // Admin dapat memindahkan kepemilikan bila diperlukan.
        $adminId = DB::table('users')
            ->where('user_type', 'admin')
            ->orderBy('id')
            ->value('id');

        if ($adminId !== null) {
            DB::table('users')
                ->where('user_type', 'client')
                ->whereNull('created_by')
                ->update(['created_by' => $adminId, 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['created_by']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
