<?php

namespace App\Modules\Absensi\Services;

use App\Modules\Absensi\Exceptions\ApiException;
use App\Modules\Absensi\Models\Anak;
use App\Modules\Absensi\Models\Evaluasi;
use App\Modules\Absensi\Models\Kegiatan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Penyimpanan nilai evaluasi — parity dengan `semarak_save_evaluasi` dan
 * `semarak_save_evaluasi_batch` di aplikasi native.
 */
class EvaluasiService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function save(array $payload): array
    {
        $anakId = (string) ($payload['anak_id'] ?? '');
        $kegiatanId = (int) ($payload['kegiatan_id'] ?? 0);
        $nilai = strtolower((string) ($payload['nilai'] ?? ''));
        $tanggal = (string) ($payload['tanggal'] ?? date('Y-m-d'));

        if ($anakId === '' || $kegiatanId <= 0 || ! in_array($nilai, Evaluasi::NILAI, true)) {
            throw new ApiException('Data evaluasi tidak valid.');
        }

        if (! Anak::query()->whereKey($anakId)->exists()) {
            throw new ApiException('Anak tidak ditemukan.');
        }

        if (! Kegiatan::query()->whereKey($kegiatanId)->exists()) {
            throw new ApiException('Kegiatan tidak ditemukan.');
        }

        Evaluasi::query()->updateOrCreate(
            [
                'anak_id' => $anakId,
                'kegiatan_id' => $kegiatanId,
                'tanggal' => $tanggal,
            ],
            [
                'nilai' => $nilai,
            ]
        );

        return [
            'success' => true,
            'message' => 'Evaluasi berhasil disimpan',
            'payload' => [
                'anak_id' => $anakId,
                'kegiatan_id' => $kegiatanId,
                'tanggal' => $tanggal,
                'nilai' => $nilai,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function saveBatch(array $items): array
    {
        if ($items === []) {
            throw new ApiException('Pilih nilai untuk setidaknya satu anak sebelum menyimpan.');
        }

        DB::connection('semarak')->transaction(function () use ($items): void {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    throw new ApiException('Data evaluasi tidak valid.');
                }

                $this->save($item);
            }
        });

        Log::info('absensi.evaluasi.batch', ['jumlah' => count($items), 'oleh' => auth()->id()]);

        return [
            'success' => true,
            'message' => 'Penilaian berhasil disimpan.',
            'saved' => count($items),
        ];
    }
}
