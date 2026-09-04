<?php

namespace App\Filament\Resources\MasterTrainingResource\Pages;

use App\Filament\Resources\MasterTrainingResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageMasterTrainings extends ManageRecords
{
    protected static string $resource = MasterTrainingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
