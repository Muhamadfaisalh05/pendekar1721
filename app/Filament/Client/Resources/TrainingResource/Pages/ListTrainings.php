<?php

namespace App\Filament\Client\Resources\TrainingResource\Pages;

use App\Filament\Client\Resources\TrainingResource;
use Filament\Resources\Pages\ListRecords;

class ListTrainings extends ListRecords
{
    protected static string $resource = TrainingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action
        ];
    }
}
