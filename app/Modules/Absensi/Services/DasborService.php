<?php

namespace App\Modules\Absensi\Services;

use App\Modules\Absensi\Models\Anak;
use App\Modules\Absensi\Models\Kegiatan;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan dasbor — parity dengan `semarak_get_dashboard_summary`.
 */
class DasborService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dashboard(array $filters): array
    {
        $startDate = (string) ($filters['mulai'] ?? '');
        $endDate = (string) ($filters['sampai'] ?? '');
        $angkatan = trim((string) ($filters['angkatan'] ?? ''));
        $statusAnak = trim((string) ($filters['status_anak'] ?? ''));

        $status = [
            'sesuai' => 0,
            'arahan' => 0,
            'telat' => 0,
            'tidak' => 0,
            'libur' => 0,
        ];

        $rows = $this->baseQuery($startDate, $endDate, $angkatan, $statusAnak)
            ->select('e.nilai')
            ->get();

        foreach ($rows as $row) {
            $nilai = strtolower((string) $row->nilai);

            if (array_key_exists($nilai, $status)) {
                $status[$nilai]++;
            }
        }

        $totalPenilaian = $rows->count();
        $total = max(1, $totalPenilaian - $status['libur']);

        $tren = $this->baseQuery($startDate, $endDate, $angkatan, $statusAnak)
            ->selectRaw(
                "DATE(e.tanggal) AS tanggal,
                 SUM(CASE WHEN e.nilai <> 'libur' THEN 1 ELSE 0 END) AS total,
                 SUM(CASE WHEN e.nilai = 'sesuai' THEN 1 ELSE 0 END) AS sesuai"
            )
            ->groupByRaw('DATE(e.tanggal)')
            ->orderByRaw('DATE(e.tanggal)')
            ->get()
            ->map(static fn ($row): array => [
                'tanggal' => (string) $row->tanggal,
                'total' => (string) $row->total,
                'sesuai' => (string) $row->sesuai,
            ])
            ->all();

        return [
            'total_anak' => $this->countAnak($angkatan, $statusAnak),
            'total_kegiatan' => Kegiatan::query()->count(),
            'status' => $status,
            'presentase' => [
                'sesuai' => round(($status['sesuai'] / $total) * 100, 1),
                'arahan' => round(($status['arahan'] / $total) * 100, 1),
                'telat' => round(($status['telat'] / $total) * 100, 1),
                'tidak' => round(($status['tidak'] / $total) * 100, 1),
            ],
            'total_penilaian' => $totalPenilaian,
            'tren' => $tren,
        ];
    }

    private function baseQuery(string $startDate, string $endDate, string $angkatan, string $statusAnak): Builder
    {
        $query = DB::connection('semarak')
            ->table('evaluasi as e')
            ->join('anak as a', 'a.id', '=', 'e.anak_id');

        if ($startDate !== '') {
            $query->where('e.tanggal', '>=', $startDate);
        }

        if ($endDate !== '') {
            $query->where('e.tanggal', '<=', $endDate);
        }

        if ($angkatan !== '') {
            $query->where('a.angkatan', $angkatan);
        }

        if ($statusAnak !== '') {
            $query->where('a.status', $statusAnak);
        }

        return $query;
    }

    private function countAnak(string $angkatan, string $statusAnak): int
    {
        $query = Anak::query();

        if ($angkatan !== '') {
            $query->where('angkatan', $angkatan);
        }

        if ($statusAnak !== '') {
            $query->where('status', $statusAnak);
        }

        return $query->count();
    }
}
