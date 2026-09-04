<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MasterEducationDegreeResource\Pages;
use App\Filament\Resources\MasterEducationDegreeResource\RelationManagers;
use App\Models\MasterEducationDegree;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MasterEducationDegreeResource extends Resource
{
    protected static ?string $model = MasterEducationDegree::class;

    protected static ?string $modelLabel = 'Jenjang Pendidikan';

    protected static ?string $pluralModelLabel = 'Jenjang Pendidikan';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Master Data';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageMasterEducationDegrees::route('/'),
        ];
    }
}
