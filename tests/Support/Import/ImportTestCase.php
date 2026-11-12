<?php

namespace Tests\Support\Import;

use App\Enums\UserStatus;
use App\Models\User;
use App\Modules\Absensi\Models\Anak;
use App\Services\ImportTemplateService;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\Absensi\AbsensiTestCase;

/**
 * Dasar pengujian alur Import Data (mode Remaja Binaan & Klien Umum).
 *
 * Menyediakan:
 * - koneksi `semarak` in-memory + data awal (lewat AbsensiTestCase),
 * - helper membuat admin, menambah anak, dan menulis file Excel uji,
 * - akses ke layanan pembuat template.
 *
 * Semua file uji ditulis ke storage/app/testing-import agar tidak menyentuh
 * direktori unduhan sungguhan; file dibersihkan pada tearDown.
 */
abstract class ImportTestCase extends AbsensiTestCase
{
    private ?string $folderUji = null;

    protected function tearDown(): void
    {
        if ($this->folderUji !== null && is_dir($this->folderUji)) {
            foreach (glob($this->folderUji.'/*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($this->folderUji);
        }

        parent::tearDown();
    }

    protected function admin(): User
    {
        return User::create([
            'name' => 'Admin Import',
            'email' => 'admin-import@example.test',
            'password' => 'Rahasia#12345',
            'user_type' => User::ROLE_ADMIN,
            'status' => UserStatus::Published,
        ]);
    }

    protected function tambahAnak(string $id, string $nama, string $asal = 'Bandung', string $angkatan = 'Angkatan III'): void
    {
        DB::connection('semarak')->table('anak')->insert([
            'id' => $id,
            'nama' => $nama,
            'jenis_kelamin' => 'Laki-laki',
            'asal_daerah' => $asal,
            'umur' => 17,
            'agama' => 'Islam',
            'angkatan' => $angkatan,
            'status' => Anak::STATUS_AKTIF,
        ]);
    }

    protected function layananTemplate(): ImportTemplateService
    {
        return app(ImportTemplateService::class);
    }

    /**
     * Folder sementara untuk file uji (dibersihkan pada tearDown).
     */
    protected function folderUji(): string
    {
        if ($this->folderUji === null) {
            $this->folderUji = storage_path('app/testing-import-'.uniqid('', true));

            if (! is_dir($this->folderUji)) {
                mkdir($this->folderUji, 0775, true);
            }
        }

        return $this->folderUji;
    }

    /**
     * Tulis file Excel sederhana (header di baris 1) untuk pengujian parser.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $baris
     */
    protected function tulisXlsx(array $headers, array $baris, string $namaBerkas = 'uji.xlsx'): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray(array_values($headers), null, 'A1');

        foreach (array_values($baris) as $index => $isiBaris) {
            $sheet->fromArray(array_values($isiBaris), null, 'A'.($index + 2));
        }

        $path = $this->folderUji().DIRECTORY_SEPARATOR.$namaBerkas;
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
