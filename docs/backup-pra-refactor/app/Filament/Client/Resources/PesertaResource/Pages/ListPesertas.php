<?php

namespace App\Filament\Client\Resources\PesertaResource\Pages;

use App\Filament\Client\Resources\PesertaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPesertas extends ListRecords
{
    protected static string $resource = PesertaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Peserta Manual')
                ->icon('heroicon-o-plus'),
        ];
    }
}
