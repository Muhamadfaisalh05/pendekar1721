<?php

namespace App\Modules\Absensi\Services;

use App\Modules\Absensi\Models\Anak;
use App\Modules\Absensi\Models\MasaPembinaan;

/**
 * Masa pembinaan — parity dengan `semarak_init_schema()` di aplikasi native:
 * anak pada angkatan yang tanggal selesainya sudah lewat otomatis berstatus `Lulus`.
 */
class PembinaanService
{
    public function luluskanAngkatanSelesai(): int
    {
        $today = now()->toDateString();

        $cohorts = MasaPembinaan::query()
            ->whereNotNull('tanggal_selesai')
            ->where('tanggal_selesai', '<=', $today)
            ->pluck('angkatan')
            ->all();

        if ($cohorts === []) {
            return 0;
        }

        return Anak::query()
            ->whereIn('angkatan', $cohorts)
            ->where('status', Anak::STATUS_AKTIF)
            ->update(['status' => Anak::STATUS_LULUS]);
    }
}
