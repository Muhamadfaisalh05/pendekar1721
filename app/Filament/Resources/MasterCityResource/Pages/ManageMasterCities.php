<?php

namespace App\Filament\Resources\MasterCityResource\Pages;

use App\Filament\Resources\MasterCityResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageMasterCities extends ManageRecords
{
    protected static string $resource = MasterCityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
