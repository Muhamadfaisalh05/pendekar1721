<?php

namespace Tests\Feature\Security;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * SEC-TEST 06 — Hardening alur import (FASE 3).
 *
 * Regresi yang dijaga:
 * - tidak ada password default "password123" lagi,
 * - jumlah baris import dibatasi,
 * - kata sandi awal operator diverifikasi terhadap IMPORT_PASSWORD_HASH,
 * - pemilik data (created_by) tercatat dari pengimpor.
 */
class ImportHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ImportService
    {
        return new ImportService();
    }

    public function test_import_dibatasi_jumlah_baris_maksimal(): void
    {
        $csv = "Nama,Email\n";

        for ($i = 1; $i <= 2001; $i++) {
            $csv .= "Peserta {$i},peserta{$i}@example.test\n";
        }

        $path = tempnam(sys_get_temp_dir(), 'import_probe') . '.csv';
        file_put_contents($path, $csv);

        try {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('melebihi batas');

            $this->service()->parseAndValidate($path, 'csv');
        } finally {
            @unlink($path);
        }
    }

    public function test_peserta_impor_tidak_memakai_password_default_yang_diketahui(): void
    {
        $this->service()->importData([
            ['name' => 'Peserta Impor', 'email' => 'peserta.impor@example.test'],
        ]);

        $user = User::where('email', 'peserta.impor@example.test')->firstOrFail();

        $this->assertSame('client', $user->user_type);
        $this->assertSame(UserStatus::Published, $user->status);
        $this->assertFalse(Hash::check('password123', $user->password));
    }

    public function test_kata_sandi_awal_diverifikasi_terhadap_hash_di_server(): void
    {
        config(['pendekar.import.password_hash' => Hash::make('Rahasia#2026')]);

        $this->assertTrue(ImportService::initialPasswordMatches(null));
        $this->assertTrue(ImportService::initialPasswordMatches('Rahasia#2026'));
        $this->assertFalse(ImportService::initialPasswordMatches('password123'));

        $this->service()->importData(
            [['name' => 'Peserta Sandi Awal', 'email' => 'sandi.awal@example.test']],
            [],
            null,
            'Rahasia#2026'
        );

        $user = User::where('email', 'sandi.awal@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('Rahasia#2026', $user->password));
        $this->assertFalse(Hash::check('password123', $user->password));
    }

    public function test_kata_sandi_awal_ditolak_bila_server_belum_mengonfigurasi_hash(): void
    {
        config(['pendekar.import.password_hash' => null]);

        $this->assertFalse(ImportService::initialPasswordMatches('apa-saja'));
        $this->assertTrue(ImportService::initialPasswordMatches(null));
    }

    public function test_pemilik_data_tercatat_dari_pengimpor(): void
    {
        $klien = User::create([
            'name' => 'Klien Pengimpor',
            'email' => 'klien.pengimpor@example.test',
            'password' => bcrypt('Secret#12345'),
            'user_type' => 'client',
            'status' => UserStatus::Published->value,
        ]);

        $this->service()->importData(
            [['name' => 'Peserta Milik Klien', 'email' => 'milik.klien@example.test']],
            [],
            $klien->id
        );

        $peserta = User::where('email', 'milik.klien@example.test')->firstOrFail();

        $this->assertSame($klien->id, $peserta->created_by);
        $this->assertTrue($peserta->creator->is($klien));
    }
}
