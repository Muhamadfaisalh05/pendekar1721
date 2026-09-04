<?php

namespace App\Filament\Resources\MasterEducationDegreeResource\Pages;

use App\Filament\Resources\MasterEducationDegreeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageMasterEducationDegrees extends ManageRecords
{
    protected static string $resource = MasterEducationDegreeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
