<?php

namespace App\Modules\Absensi\Services;

use App\Modules\Absensi\Exceptions\ApiException;
use App\Modules\Absensi\Models\Anak;
use App\Modules\Absensi\Models\CatatanRapor;

/**
 * Data rapor & catatan periode — parity dengan `semarak_get_report_data`
 * dan `semarak_save_report_notes`.
 */
class RaporService
{
    public function __construct(private readonly RingkasanService $ringkasan, private readonly JadwalService $jadwal) {}

    /**
     * @return array<string, mixed>
     */
    public function report(string $anakId, string $startDate = '', string $endDate = ''): array
    {
        // Native menghitung ringkasan lebih dulu dengan rentang asli (bisa kosong).
        $ringkasan = $this->ringkasan->summary($anakId, $startDate, $endDate);

        $anak = Anak::query()->find($anakId);

        if ($startDate === '') {
            $startDate = $endDate !== '' ? $endDate : date('Y-m-d');
        }

        if ($endDate === '') {
            $endDate = $startDate;
        }

        $notes = CatatanRapor::query()
            ->where('anak_id', $anakId)
            ->where('periode_mulai', $startDate)
            ->where('periode_selesai', $endDate)
            ->first();

        return [
            'anak' => $anak?->toArray() ?? false,
            'ringkasan' => $ringkasan,
            'tanggal_catat' => $endDate,
            'periode_mulai' => $startDate,
            'periode_selesai' => $endDate,
            'catatan' => $notes?->catatan ?? '',
            'rekomendasi' => $notes?->rekomendasi ?? '',
            'standar_evaluasi' => ['Berehan', 'Motekar', 'Calakan', 'Berseka', 'Waringkas'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function saveNotes(array $payload): array
    {
        $anakId = trim((string) ($payload['anak_id'] ?? ''));
        $startDate = (string) ($payload['periode_mulai'] ?? '');
        $endDate = (string) ($payload['periode_selesai'] ?? '');
        $catatan = trim((string) ($payload['catatan'] ?? ''));
        $rekomendasi = trim((string) ($payload['rekomendasi'] ?? ''));

        if (! $this->jadwal->isValidDate($startDate) || ! $this->jadwal->isValidDate($endDate)) {
            throw new ApiException('Periode rapor tidak valid.');
        }

        if ($startDate > $endDate) {
            throw new ApiException('Tanggal mulai tidak boleh melewati tanggal selesai.');
        }

        CatatanRapor::query()->updateOrCreate(
            [
                'anak_id' => $anakId,
                'periode_mulai' => $startDate,
                'periode_selesai' => $endDate,
            ],
            [
                'catatan' => $catatan,
                'rekomendasi' => $rekomendasi,
            ]
        );

        return ['success' => true, 'message' => 'Catatan dan rekomendasi rapor berhasil disimpan.'];
    }
}
