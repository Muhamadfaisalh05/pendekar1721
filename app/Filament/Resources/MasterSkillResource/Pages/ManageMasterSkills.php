<?php

namespace App\Filament\Resources\MasterSkillResource\Pages;

use App\Filament\Resources\MasterSkillResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageMasterSkills extends ManageRecords
{
    protected static string $resource = MasterSkillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
