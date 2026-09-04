<?php

namespace App\Filament\Resources\MasterReligionResource\Pages;

use App\Filament\Resources\MasterReligionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageMasterReligions extends ManageRecords
{
    protected static string $resource = MasterReligionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
