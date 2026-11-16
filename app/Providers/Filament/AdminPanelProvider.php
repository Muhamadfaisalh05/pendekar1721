<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\AksesCepatWidget;
use App\Filament\Widgets\BinaanPerAngkatanChart;
use App\Filament\Widgets\KomposisiStatusAkunChart;
use App\Filament\Widgets\PerluTindakanWidget;
use App\Filament\Widgets\PertumbuhanAkunChart;
use App\Http\Middleware\AuthenticatePanel;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Navigasi internal panel memakai mode SPA Livewire
            // (`wire:navigate`): CSS/JS tidak diunduh dan dieksekusi ulang
            // setiap pindah halaman, sehingga perpindahan terasa jauh lebih
            // cepat. Panel Absensi native tidak terpengaruh karena bukan
            // bagian dari panel Filament ini.
            ->spa()
            ->brandName('PENDEKAR1721 - Admin')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->maxContentWidth('full')
            ->darkMode(false)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // Urutan tampil diatur oleh `protected static ?int $sort` masing-masing.
                // Semua widget dashboard bernilai >= 1 supaya kartu sambutan bawaan
                // Filament (AccountWidget, sort -3) tetap berada di paling atas.
                AdminStatsOverview::class,
                BinaanPerAngkatanChart::class,
                KomposisiStatusAkunChart::class,
                PertumbuhanAkunChart::class,
                PerluTindakanWidget::class,
                AksesCepatWidget::class,
                Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AuthenticatePanel::class,
            ]);
    }
}
