<?php

namespace App\Modules\Absensi\Http\Controllers;

use App\Modules\Absensi\Models\Anak;
use App\Modules\Absensi\Models\Kegiatan;
use Illuminate\Contracts\View\View;

/**
 * Halaman modul absensi (Blade). Data awal yang dibutuhkan dropdown dimuat
 * di server seperti aplikasi native (`semarak_get_anak()` / `_kegiatan_rows()`).
 */
class PageController
{
    public function dashboard(): View
    {
        return view('absensi.dashboard', [
            'angkatanList' => Anak::query()->distinct()->orderBy('angkatan')->pluck('angkatan'),
        ]);
    }

    public function input(): View
    {
        return view('absensi.input', [
            'anakList' => Anak::query()->orderBy('nama')->get(),
            'kegiatanList' => Kegiatan::query()->orderBy('id')->get(),
        ]);
    }

    public function anak(): View
    {
        return view('absensi.anak', [
            'anakList' => Anak::query()->orderBy('nama')->get(),
        ]);
    }

    public function summary(): View
    {
        return view('absensi.summary', [
            'anakList' => Anak::query()->orderBy('nama')->get(),
        ]);
    }

    public function rapor(): View
    {
        return view('absensi.rapor', [
            'anakList' => Anak::query()->orderBy('nama')->get(),
        ]);
    }

    public function pengaturan(): View
    {
        return view('absensi.pengaturan', [
            'kegiatanList' => Kegiatan::query()->orderBy('id')->get(),
            'angkatanList' => Anak::query()->distinct()->orderBy('angkatan')->pluck('angkatan'),
        ]);
    }
}
