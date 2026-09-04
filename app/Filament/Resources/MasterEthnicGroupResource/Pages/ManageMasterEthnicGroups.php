<?php

namespace App\Filament\Resources\MasterEthnicGroupResource\Pages;

use App\Filament\Resources\MasterEthnicGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageMasterEthnicGroups extends ManageRecords
{
    protected static string $resource = MasterEthnicGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
