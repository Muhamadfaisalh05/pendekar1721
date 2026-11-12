<?php

namespace App\Filament\Resources\AnakResource\Pages;

use App\Filament\Resources\AnakResource;
use App\Filament\Support\PeringatanMigrasi;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAnak extends ManageRecords
{
    protected static string $resource = AnakResource::class;

    /**
     * Peringatan sekali per halaman bila migrasi Fase 2 belum dijalankan.
     */
    public function mount(): void
    {
        parent::mount();

        PeringatanMigrasi::tampilkanSekali();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
