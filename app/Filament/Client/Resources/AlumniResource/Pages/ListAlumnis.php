<?php

namespace App\Filament\Client\Resources\AlumniResource\Pages;

use App\Filament\Client\Resources\AlumniResource;
use Filament\Resources\Pages\ListRecords;

class ListAlumnis extends ListRecords
{
    protected static string $resource = AlumniResource::class;

    /**
     * Direktori alumni bersifat opsional: bila tidak diaktifkan lewat config,
     * URL `/klien/alumni` harus tampak seperti halaman yang tidak ada (404),
     * bukan halaman kosong atau 403 yang membocorkan keberadaan fitur.
     */
    protected function authorizeAccess(): void
    {
        abort_unless(AlumniResource::isDirectoryEnabled(), 404);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Read-only: tanpa aksi pembuatan data.
        ];
    }
}
