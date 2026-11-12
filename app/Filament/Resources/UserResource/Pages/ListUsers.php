<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Filament\Support\PeringatanMigrasi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

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
