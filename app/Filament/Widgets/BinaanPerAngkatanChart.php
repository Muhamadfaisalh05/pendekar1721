<?php

namespace App\Filament\Widgets;

use App\Services\DasborAdminService;
use Filament\Widgets\ChartWidget;

/**
 * Grafik batang: jumlah anak binaan per angkatan (10 angkatan terbesar).
 * Angka agregat saja — tidak ada nama anak.
 */
class BinaanPerAngkatanChart extends ChartWidget
{
    protected static ?string $heading = 'Binaan per Angkatan';

    protected static ?string $description = '10 angkatan dengan jumlah binaan terbanyak';

    // Dirender langsung (bukan lazy): angka tampil pada paint pertama dan bisa
    // diuji lewat GET /admin tanpa permintaan Livewire tambahan.
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '260px';

    protected static string $color = 'warning';

    // Grafik batang memakai lebar penuh: label angkatan panjang dan mudah dibaca.
    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $grafik = app(DasborAdminService::class)->ringkasan()['binaan_per_angkatan'];

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah binaan',
                    'data' => $grafik['data'] ?? [],
                    'borderRadius' => 6,
                    'maxBarThickness' => 32,
                ],
            ],
            'labels' => $grafik['labels'] ?? [],
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

    public function getDescription(): ?string
    {
        if ((app(DasborAdminService::class)->ringkasan()['binaan_per_angkatan'] ?? null) === null) {
            return 'Data belum tersedia — periksa koneksi database SEMARAK.';
        }

        return parent::getDescription();
    }
}
