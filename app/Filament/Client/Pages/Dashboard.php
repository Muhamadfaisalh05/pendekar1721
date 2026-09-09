<?php

namespace App\Filament\Client\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Models\User;
use App\Models\MasterTraining;
use Illuminate\Contracts\View\View;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.client.pages.dashboard';
    
    protected static ?string $title = 'Dashboard Klien';
    
    public static function canAccess(): bool
    {
        return auth()->user()->user_type === 'client';
    }
    
    public function getPesertaTerbaru()
    {
        return User::where('user_type', 'client')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }
    
    public function getStatistik()
    {
        return [
            'total_peserta' => User::where('user_type', 'client')->count(),
            'total_pelatihan' => MasterTraining::count(),
            'tersedia' => User::where('user_type', 'client')
                ->whereHas('userProfile', function ($query) {
                    $query->where('hire_status', '1');
                })->count(),
        ];
    }
}
