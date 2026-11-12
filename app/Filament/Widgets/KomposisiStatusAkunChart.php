<?php

namespace App\Filament\Widgets;

use App\Services\DasborAdminService;
use Filament\Widgets\ChartWidget;

/**
 * Grafik donat: komposisi status akun Klien (Draft / Aktif / Diarsipkan).
 */
class KomposisiStatusAkunChart extends ChartWidget
{
    protected static ?string $heading = 'Komposisi Status Akun Klien';

    protected static ?string $description = 'Draft, aktif, dan diarsipkan';

    // Dirender langsung (bukan lazy): angka tampil pada paint pertama dan bisa
    // diuji lewat GET /admin tanpa permintaan Livewire tambahan.
    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '260px';

    protected static string $color = 'primary';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $grafik = app(DasborAdminService::class)->ringkasan()['komposisi_status'];

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah akun',
                    'data' => $grafik['data'],
                    'backgroundColor' => ['#f59e0b', '#10b981', '#9ca3af'],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $grafik['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
            'cutout' => '62%',
        ];
    }
}
