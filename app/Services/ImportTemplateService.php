<?php

namespace App\Services;

use App\Models\MasterCity;
use App\Models\MasterEducationDegree;
use App\Models\MasterReligion;
use App\Models\MasterTraining;
use App\Modules\Absensi\Models\Anak;
use App\Support\Import\TemplateExcel;
use App\Support\Import\TemplateFormulirWord;
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
     * Nama berkas FORMULIR per anak: template-formulir-remaja-binaan-YYYYMMDD.docx.
     */
    public function namaBerkasFormulir(string $ekstensi = 'docx'): string
    {
        return 'template-formulir-remaja-binaan-'.now()->format('Ymd').'.'.strtolower($ekstensi);
    }

    /**
     * Buat formulir Word (.docx, satu blok per anak) dan kembalikan path sementaranya.
     */
    public function buatFormulirWord(): string
    {
        $path = $this->pathSementara($this->namaBerkasFormulir());
        $referensi = $this->referensi();

        (new TemplateFormulirWord($referensi, $this->labelMasterKosong($referensi)))->simpan($path);

        return $path;
    }

    /**
     * Daftar pilihan untuk sheet DAFTAR (Excel) dan dropdown Word.
     *
     * SENGAJA tanpa daftar cadangan: bila master benar-benar kosong, dropdown
     * hanya berisi placeholder dan dokumen mencantumkan catatan "master belum
     * tersedia". Daftar cadangan lama membuat operator memilih nilai yang
     * tidak ada di database, sehingga import gagal saat penautan.
     *
     * @return array<string, array<int, string>>
     */
    public function referensi(): array
    {
        $wilayah = $this->daftarDariDatabase(MasterCity::class);

        return [
            'agama' => $this->daftarAgama(),
            'jenis_kelamin' => ['L', 'P'],
            'pendidikan' => $this->daftarDariDatabase(MasterEducationDegree::class),
            'pelatihan' => $this->daftarDariDatabase(MasterTraining::class),
            'wilayah' => $wilayah,
            'kota_ktp' => $wilayah,
            'status_pekerjaan' => ['Belum Bekerja', 'Sedang Bekerja'],
            'status_profil' => ['Tersedia', 'Belum'],
        ];
    }

    /**
     * Label kolom yang daftar masternya kosong (dipakai catatan formulir).
     *
     * @param  array<string, array<int, string>>  $referensi
     * @return array<int, string>
     */
    private function labelMasterKosong(array $referensi): array
    {
        $label = [
            'agama' => 'Agama',
            'pendidikan' => 'Pendidikan',
            'pelatihan' => 'Pelatihan',
            'wilayah' => 'Penempatan Wilayah',
            'kota_ktp' => 'Kota KTP',
        ];

        $kosong = [];

        foreach ($label as $kunci => $teks) {
            if (($referensi[$kunci] ?? []) === []) {
                $kosong[] = $teks;
            }
        }

        return $kosong;
    }

    /**
     * Agama: master agama, dilengkapi nilai yang sudah dipakai data anak.
     *
     * @return array<int, string>
     */
    private function daftarAgama(): array
    {
        $agama = $this->daftarDariDatabase(MasterReligion::class);

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

        return array_values(array_unique(array_filter(array_map(
            fn ($item): string => trim((string) $item),
            $agama
        ), fn (string $item): bool => $item !== '')));
    }

    /**
     * Ambil kolom `title` dari master; kosong bila master belum tersedia.
     *
     * @param  class-string<Model>  $kelas
     * @return array<int, string>
     */
    private function daftarDariDatabase(string $kelas): array
    {
        try {
            return $kelas::query()
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

            return [];
        }
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
