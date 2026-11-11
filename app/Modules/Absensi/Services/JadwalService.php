<?php

namespace App\Modules\Absensi\Services;

use App\Modules\Absensi\Exceptions\ApiException;
use App\Modules\Absensi\Models\JadwalKegiatan;
use App\Modules\Absensi\Models\Kegiatan;
use App\Modules\Absensi\Models\MasaPembinaan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pengaturan jadwal kegiatan & masa pembinaan — parity dengan
 * `semarak_get_settings` dan `semarak_save_settings` di aplikasi native.
 */
class JadwalService
{
    /**
     * @return array{jadwal: array<int, array<string, mixed>>, angkatan: array<int, array<string, mixed>>}
     */
    public function settings(): array
    {
        $scheduleByActivity = [];

        foreach (JadwalKegiatan::query()->get() as $jadwal) {
            $scheduleByActivity[(int) $jadwal->kegiatan_id] = [
                'aturan' => $jadwal->aturan,
                'hari' => $jadwal->hari(),
                'rentang' => $jadwal->rentang(),
            ];
        }

        $rows = DB::connection('semarak')
            ->table('anak as a')
            ->leftJoin('masa_pembinaan as m', 'm.angkatan', '=', 'a.angkatan')
            ->selectRaw('a.angkatan, COUNT(a.id) AS total_anak, m.tanggal_selesai')
            ->groupBy('a.angkatan', 'm.tanggal_selesai')
            ->orderBy('a.angkatan')
            ->get();

        $angkatan = $rows->map(static fn ($row): array => [
            'angkatan' => $row->angkatan,
            'total_anak' => (int) $row->total_anak,
            'tanggal_selesai' => $row->tanggal_selesai,
        ])->all();

        return [
            'jadwal' => $scheduleByActivity,
            'angkatan' => $angkatan,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function save(array $payload): array
    {
        $schedules = $payload['jadwal'] ?? [];

        if (! is_array($schedules)) {
            throw new ApiException('Format jadwal tidak valid.');
        }

        DB::connection('semarak')->transaction(function () use ($schedules, $payload): void {
            foreach ($schedules as $activityId => $schedule) {
                $activityId = (int) $activityId;
                $rule = is_array($schedule) ? (string) ($schedule['aturan'] ?? '') : '';

                if ($activityId <= 0 || ! is_array($schedule) || ! in_array($rule, JadwalKegiatan::ATURAN, true)) {
                    throw new ApiException('Aturan jadwal tidak valid.');
                }

                if (! Kegiatan::query()->whereKey($activityId)->exists()) {
                    throw new ApiException('Kegiatan jadwal tidak ditemukan.');
                }

                $days = $schedule['hari'] ?? [];
                $ranges = $schedule['rentang'] ?? [];

                if (! is_array($days) || ! is_array($ranges)) {
                    throw new ApiException('Hari atau rentang jadwal tidak valid.');
                }

                foreach ($days as $day) {
                    if (! in_array((string) $day, ['1', '2', '3', '4', '5', '6', '7'], true)) {
                        throw new ApiException('Hari jadwal tidak valid.');
                    }
                }

                foreach ($ranges as $range) {
                    $fromValue = (string) ($range['mulai'] ?? '');
                    $toValue = (string) ($range['selesai'] ?? '');

                    if (! $this->isValidDate($fromValue) || ! $this->isValidDate($toValue) || $fromValue > $toValue) {
                        throw new ApiException('Rentang tanggal jadwal tidak valid.');
                    }
                }

                JadwalKegiatan::query()->updateOrCreate(
                    ['kegiatan_id' => $activityId],
                    [
                        'aturan' => $rule,
                        'hari_json' => array_values(array_map('strval', $days)),
                        'rentang_json' => array_values(array_map(static fn ($range): array => [
                            'mulai' => (string) ($range['mulai'] ?? ''),
                            'selesai' => (string) ($range['selesai'] ?? ''),
                        ], $ranges)),
                    ]
                );
            }

            $cohorts = $payload['angkatan'] ?? [];

            if (! is_array($cohorts)) {
                throw new ApiException('Format masa pembinaan tidak valid.');
            }

            foreach ($cohorts as $cohort => $endDate) {
                $cohort = trim((string) $cohort);

                if ($cohort === '') {
                    throw new ApiException('Nama angkatan tidak valid.');
                }

                if ($endDate !== '' && ! $this->isValidDate((string) $endDate)) {
                    throw new ApiException('Tanggal selesai pembinaan tidak valid.');
                }

                MasaPembinaan::query()->updateOrCreate(
                    ['angkatan' => $cohort],
                    ['tanggal_selesai' => $endDate !== '' ? (string) $endDate : null]
                );
            }
        });

        return ['success' => true, 'message' => 'Pengaturan berhasil disimpan.'];
    }

    /**
     * Port `isActivityScheduled()` dari absensi.php native (dipakai sisi tampilan).
     *
     * @param  array<int, array<string, mixed>>  $schedules  peta kegiatan_id => {aturan, hari, rentang}
     */
    public function isScheduledFor(int $activityId, string $date, array $schedules = []): bool
    {
        $schedule = $schedules[$activityId]
            ?? ['aturan' => JadwalKegiatan::ATURAN_SETIAP_HARI, 'hari' => [], 'rentang' => []];

        $weekday = (string) Carbon::createFromFormat('Y-m-d', $date)->dayOfWeekIso;

        return match ($schedule['aturan']) {
            JadwalKegiatan::ATURAN_HARI_KERJA => in_array($weekday, ['1', '2', '3', '4', '5'], true),
            JadwalKegiatan::ATURAN_HARI_TERTENTU => in_array($weekday, array_map('strval', (array) ($schedule['hari'] ?? [])), true),
            JadwalKegiatan::ATURAN_MULTI_RENTANG => collect((array) ($schedule['rentang'] ?? []))->contains(
                static fn ($range): bool => ($range['mulai'] ?? '') !== ''
                    && ($range['selesai'] ?? '') !== ''
                    && $date >= $range['mulai']
                    && $date <= $range['selesai']
            ),
            default => true,
        };
    }

    /**
     * Validasi tanggal `Y-m-d` ketat (setara DateTime::createFromFormat di native).
     */
    public function isValidDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $parsed = Carbon::createFromFormat('Y-m-d', $value);

        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    }
}

