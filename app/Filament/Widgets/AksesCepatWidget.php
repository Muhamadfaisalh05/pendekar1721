<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ImportData;
use App\Filament\Resources\AnakResource;
use App\Filament\Resources\KegiatanResource;
use App\Filament\Resources\UserResource;
use Filament\Widgets\Widget;

/**
 * Akses cepat dashboard Admin: Import Data, Anak Binaan, User, Kegiatan.
 * Hanya tautan navigasi — tidak memuat data pengguna.
 */
class AksesCepatWidget extends Widget
{
    protected static string $view = 'filament.widgets.akses-cepat';

    // Dirender langsung (bukan lazy): angka tampil pada paint pertama dan bisa
    // diuji lewat GET /admin tanpa permintaan Livewire tambahan.
    protected static bool $isLazy = false;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'tautan' => [
                [
                    'label' => 'Import Data',
                    'keterangan' => 'Unggah template Excel / Word binaan',
                    'ikon' => 'heroicon-o-arrow-up-tray',
                    'url' => ImportData::getUrl(),
                    'latar' => '#f59e0b',
                ],
                [
                    'label' => 'Anak Binaan',
                    'keterangan' => 'Data binaan, kelulusan & akun klien',
                    'ikon' => 'heroicon-o-user-group',
                    'url' => AnakResource::getUrl('index'),
                    'latar' => '#0284c7',
                ],
                [
                    'label' => 'User',
                    'keterangan' => 'Akun admin & klien, reset kata sandi',
                    'ikon' => 'heroicon-o-users',
                    'url' => UserResource::getUrl('index'),
                    'latar' => '#059669',
                ],
                [
                    'label' => 'Kegiatan',
                    'keterangan' => 'Daftar kegiatan harian binaan',
                    'ikon' => 'heroicon-o-calendar-days',
                    'url' => KegiatanResource::getUrl('index'),
                    'latar' => '#7c3aed',
                ],
            ],
        ];
    }
}
