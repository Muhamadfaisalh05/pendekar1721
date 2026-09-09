<?php

namespace App\Filament\Client\Resources\PesertaResource\Pages;

use App\Filament\Client\Resources\PesertaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePeserta extends CreateRecord
{
    protected static string $resource = PesertaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_type'] = 'client';
        $data['status'] = 'published';
        return $data;
    }
}
