<?php

namespace App\Filament\Client\Resources\PesertaResource\Pages;

use App\Filament\Client\Resources\PesertaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPeserta extends EditRecord
{
    protected static string $resource = PesertaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
