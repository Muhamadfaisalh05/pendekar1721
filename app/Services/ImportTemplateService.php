<?php

namespace App\Services;

use App\Models\MasterCity;
use App\Models\MasterEducationDegree;
use App\Models\MasterReligion;
use App\Models\MasterTraining;
use App\Modules\Absensi\Models\Anak;
use App\Support\Import\TemplateExcel;
use App\Support\Import\TemplateWord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Pembuat template import (Excel & Word) untuk mode Remaja Binaan.
 *
 * Halaman Import Data memakai layanan ini untuk tombol "Download Template".
 * Daftar pilihan dropdown (agama, pendidikan, pelatihan, wilayah) dibaca dari
 * database saat template dibuat, sehingga nilai yang ditawarkan ke operator
 * memang dikenali sistem.
 */
class ImportTemplateService
{
    /**
     * Nama berkas unduhan: template-import-remaja-binaan-YYYYMMDD.xlsx/.docx.
     */
    public function namaBerkas(string $ekstensi): string
    {
        return 'template-import-remaja-binaan-'.now()->format('Ymd').'.'.strtolower($ekstensi);
    }

    /**
     * Buat template Excel (.xlsx) dan kembalikan path sementaranya.
     */
    public function buatExcel(): string
    {
        $path = $this->pathSementara($this->namaBerkas('xlsx'));

        (new TemplateExcel($this->referensi()))->simpan($path);

        return $path;
    }

    /**
     * Buat template Word (.docx) dan kembalikan path sementaranya.
     */
    public function buatWord(): string
    {
        $path = $this->pathSementara($this->namaBerkas('docx'));

        (new TemplateWord($this->referensi()))->simpan($path);

        return $path;
    }

    /**
     * Daftar pilihan untuk sheet DAFTAR (Excel) dan keterangan Word.
     *
     * @return array<string, array<int, string>>
     */
    public function referensi(): array
    {
        return [
            'agama' => $this->daftarAgama(),
            'jenis_kelamin' => ['L', 'P'],
            'pendidikan' => $this->daftarDariDatabase(MasterEducationDegree::class, ['SMA/SMK', 'SMP', 'SD', 'D3', 'S1']),
            'pelatihan' => $this->daftarDariDatabase(MasterTraining::class, ['Menjahit', 'Tata Boga', 'Mengetik']),
            'wilayah' => $this->daftarDariDatabase(MasterCity::class, ['Kota Bandung', 'Kota Bogor', 'Jakarta']),
        ];
    }

    /**
     * Agama: master agama, dilengkapi nilai yang sudah dipakai data anak.
     *
     * @return array<int, string>
     */
    private function daftarAgama(): array
    {
        $agama = $this->daftarDariDatabase(MasterReligion::class, []);

        try {
            // `semarak.anak` bisa belum tersedia di environment tertentu;
            // kegagalan koneksi tidak boleh menggagalkan pembuatan template.
            $dipakai = Anak::query()
                ->whereNotNull('agama')
                ->distinct()
                ->orderBy('agama')
                ->pluck('agama')
                ->all();

            $agama = array_merge($agama, array_map('strval', $dipakai));
        } catch (\Throwable $e) {
            Log::warning('import.template.agama_gagal', ['error' => $e->getMessage()]);
        }

        if ($agama === []) {
            $agama = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($item): string => trim((string) $item),
            $agama
        ), fn (string $item): bool => $item !== '')));
    }

    /**
     * Ambil kolom `title` dari master; bila kosong/gagal pakai daftar cadangan.
     *
     * @param  class-string<Model>  $kelas
     * @param  array<int, string>  $cadangan
     * @return array<int, string>
     */
    private function daftarDariDatabase(string $kelas, array $cadangan): array
    {
        try {
            $daftar = $kelas::query()
                ->whereNotNull('title')
                ->orderBy('title')
                ->pluck('title')
                ->map(fn ($item): string => trim((string) $item))
                ->filter(fn (string $item): bool => $item !== '')
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('import.template.master_gagal', ['kelas' => $kelas, 'error' => $e->getMessage()]);
            $daftar = [];
        }

        return $daftar === [] ? $cadangan : $daftar;
    }

    /**
     * Path sementara template (storage/app/import-templates).
     */
    private function pathSementara(string $namaBerkas): string
    {
        $direktori = storage_path('app/import-templates');

        if (! is_dir($direktori)) {
            mkdir($direktori, 0775, true);
        }

        return $direktori.DIRECTORY_SEPARATOR.$namaBerkas;
    }
}
