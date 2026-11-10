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

        // SECURITY (FASE 2 / Opsi A): catat pemilik data agar panel /klien
        // hanya menampilkan peserta milik klien yang membuatnya.
        $data['created_by'] = auth()->id();

        return $data;
    }
}
