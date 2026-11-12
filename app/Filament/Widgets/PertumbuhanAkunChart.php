<?php

namespace App\Filament\Widgets;

use App\Services\DasborAdminService;
use Filament\Widgets\ChartWidget;

/**
 * Grafik garis: pertumbuhan akun Klien 12 bulan terakhir.
 *
 * Satu query GROUP BY per bulan (bukan 12 query) — lihat
 * `DasborAdminService::pertumbuhanAkun12Bulan()`.
 */
class PertumbuhanAkunChart extends ChartWidget
{
    protected static ?string $heading = 'Pertumbuhan Akun Klien (12 Bulan)';

    protected static ?string $description = 'Jumlah akun Klien baru tiap bulan';

    // Dirender langsung (bukan lazy): angka tampil pada paint pertama dan bisa
    // diuji lewat GET /admin tanpa permintaan Livewire tambahan.
    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected static ?string $maxHeight = '260px';

    protected static string $color = 'success';

    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 2,
    ];

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $grafik = app(DasborAdminService::class)->ringkasan()['pertumbuhan'];

        return [
            'datasets' => [
                [
                    'label' => 'Akun baru',
                    'data' => $grafik['data'],
                    'fill' => 'start',
                    'tension' => 0.35,
                    'pointRadius' => 3,
                ],
            ],
            'labels' => $grafik['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }
}
