<?php

namespace App\Filament\Widgets;

use App\Enums\UserStatus;
use App\Filament\Resources\UserResource;
use App\Services\DasborAdminService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Kartu ringkasan dashboard Admin.
 *
 * SECURITY: seluruh angka berasal dari `DasborAdminService` yang hanya
 * menjalankan COUNT/GROUP BY — tidak ada nama/email pengguna di widget ini.
 *
 * KETAHANAN: bagian yang datanya belum tersedia (koneksi SEMARAK mati,
 * migrasi tertunda) tampil sebagai "Data belum tersedia", bukan error 500.
 */
class AdminStatsOverview extends BaseWidget
{
    // Dirender langsung (bukan lazy): angka tampil pada paint pertama dan bisa
    // diuji lewat GET /admin tanpa permintaan Livewire tambahan.
    protected static bool $isLazy = false;

    // Urutan: tepat di bawah kartu sambutan bawaan Filament (AccountWidget, -3).
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $data = app(DasborAdminService::class)->ringkasan();

        return [
            $this->kartuHitung('Anak Binaan Aktif', $data['anak_aktif'], 'Berstatus Aktif di SEMARAK', 'heroicon-o-user-group', 'warning'),
            $this->kartuHitung('Siap Diluluskan', $data['siap_diluluskan'], 'Masa pembinaan angkatannya sudah selesai', 'heroicon-o-academic-cap', 'success'),
            $this->kartuAkunDraft($data['akun_draft']),
            $this->kartuAlumni($data['alumni']),
            $this->kartuHitung('Total Akun Klien', $data['total_klien'], 'Seluruh akun panel Klien', 'heroicon-o-users', 'info'),
            $this->kartuHitung('Admin', $data['admin'], 'Administrator Pendekar', 'heroicon-o-shield-check', 'primary'),
        ];
    }

    private function kartuAkunDraft(int $jumlah): Stat
    {
        return Stat::make('Akun Draft', $jumlah)
            ->description($jumlah > 0 ? 'Menunggu kelulusan / aktivasi' : 'Tidak ada akun menunggu')
            ->descriptionIcon($jumlah > 0 ? 'heroicon-m-clock' : 'heroicon-m-check-circle')
            ->color($jumlah > 0 ? 'danger' : 'gray')
            ->icon('heroicon-o-document-text')
            // Tautan langsung ke daftar yang sudah difilter status Draft.
            ->url(UserResource::getUrl('index', [
                'tableFilters' => ['status' => ['value' => UserStatus::Draft->value]],
            ]));
    }

    /**
     * @param  array{total: int, belum_final: bool}  $alumni
     */
    private function kartuAlumni(array $alumni): Stat
    {
        return Stat::make('Alumni', $alumni['total'])
            ->description($alumni['belum_final']
                ? 'Angka sementara (belum final)'
                : 'Akun klien yang sudah lulus')
            ->descriptionIcon($alumni['belum_final'] ? 'heroicon-m-clock' : 'heroicon-m-check-badge')
            ->color($alumni['belum_final'] ? 'warning' : 'success')
            ->icon('heroicon-o-trophy');
    }

    /**
     * Satu bentuk kartu untuk angka yang bisa "belum tersedia" (`null`).
     */
    private function kartuHitung(
        string $judul,
        ?int $jumlah,
        string $keteranganTersedia,
        string $ikon,
        string $warna,
    ): Stat {
        if ($jumlah === null) {
            return Stat::make($judul, '—')
                ->description('Data belum tersedia')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('gray')
                ->icon($ikon);
        }

        return Stat::make($judul, $jumlah)
            ->description($keteranganTersedia)
            ->descriptionIcon('heroicon-m-information-circle')
            ->color($warna)
            ->icon($ikon);
    }
}
