<?php

namespace App\Modules\Absensi\Services;

use App\Modules\Absensi\Models\Anak;
use App\Modules\Absensi\Models\Evaluasi;
use App\Modules\Absensi\Models\Kegiatan;

/**
 * Ringkasan & analisis individu — parity dengan `semarak_get_summary_by_anak`.
 *
 * Catatan: native memetakan baris detail dengan `$detail[$kegiatan_id - 1]`
 * sehingga nilai hilang bila id kegiatan berlubang. Di modul ini pemetaan
 * memakai id kegiatan (hasil identik untuk data berurutan, aman untuk data
 * berlubang) — perubahan disetujui pemilik produk.
 */
class RingkasanService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(string $anakId, string $startDate = '', string $endDate = ''): array
    {
        $anak = Anak::query()->find($anakId);

        $status = [
            'sesuai' => 0,
            'arahan' => 0,
            'telat' => 0,
            'tidak' => 0,
            'libur' => 0,
        ];

        if ($anak === null) {
            return [
                'anak' => null,
                'status' => $status,
                'skor_total' => 0,
                'detail' => [],
            ];
        }

        $detail = [];

        foreach (Kegiatan::query()->orderBy('id')->get(['id', 'nama']) as $kegiatan) {
            $detail[(int) $kegiatan->id] = [
                'no' => (int) $kegiatan->id,
                'kegiatan' => $kegiatan->nama,
                'sesuai' => 0,
                'arahan' => 0,
                'telat' => 0,
                'tidak' => 0,
                'libur' => 0,
                'skor' => 0,
            ];
        }

        $query = Evaluasi::query()->where('anak_id', $anakId);

        if ($startDate !== '') {
            $query->where('tanggal', '>=', $startDate);
        }

        if ($endDate !== '') {
            $query->where('tanggal', '<=', $endDate);
        }

        foreach ($query->orderBy('kegiatan_id')->get(['kegiatan_id', 'nilai']) as $evaluasi) {
            $nilai = strtolower((string) $evaluasi->nilai);
            $kegiatanId = (int) $evaluasi->kegiatan_id;

            if (array_key_exists($nilai, $status)) {
                $status[$nilai]++;
            }

            if (isset($detail[$kegiatanId]) && array_key_exists($nilai, $detail[$kegiatanId])) {
                $detail[$kegiatanId][$nilai]++;
            }
        }

        foreach ($detail as $kegiatanId => $row) {
            $total = max(1, $row['sesuai'] + $row['arahan'] + $row['telat'] + $row['tidak']);
            $detail[$kegiatanId]['skor'] = round(($row['sesuai'] / $total) * 100, 1);
        }

        $totalNilai = max(1, array_sum($status) - $status['libur']);
        $skorTotal = round(($status['sesuai'] / $totalNilai) * 100, 1);

        return [
            'anak' => $anak->toArray(),
            'status' => $status,
            'skor_total' => $skorTotal,
            'detail' => array_values($detail),
        ];
    }
}
