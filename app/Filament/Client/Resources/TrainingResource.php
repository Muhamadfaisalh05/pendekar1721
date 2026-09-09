<?php

namespace App\Filament\Client\Resources;

use App\Filament\Client\Resources\TrainingResource\Pages;
use App\Models\MasterTraining;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrainingResource extends Resource
{
    protected static ?string $model = MasterTraining::class;

    protected static ?string $modelLabel = 'Pelatihan';
    protected static ?string $pluralModelLabel = 'Data Pelatihan';
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Nama Pelatihan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ditambahkan Pada')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                // Read-only, no actions
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrainings::route('/'),
        ];
    }
}
