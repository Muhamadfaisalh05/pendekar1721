<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MasterTrainingResource\Pages;
use App\Models\MasterTraining;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MasterTrainingResource extends Resource
{
    protected static ?string $model = MasterTraining::class;

    protected static ?string $modelLabel = 'Pelatihan';

    protected static ?string $pluralModelLabel = 'Pelatihan';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Nama Pelatihan')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description_1')
                    ->label('Deskripsi 1')
                    ->rows(4)
                    ->maxLength(5000)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description_2')
                    ->label('Deskripsi 2')
                    ->rows(4)
                    ->maxLength(5000)
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('image_path')
                    ->label('Foto Gambar')
                    ->disk('public')
                    ->directory('training-images')
                    ->visibility('public')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                    ->maxSize(5120)
                    ->imageEditor()
                    ->imageEditorAspectRatios(['16:9', '4:3', '1:1'])
                    ->imageCropAspectRatio('16:9')
                    ->imageResizeTargetWidth('1280')
                    ->imageResizeTargetHeight('720')
                    ->imagePreviewHeight('240')
                    ->openable()
                    ->downloadable()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Foto')
                    ->disk('public')
                    ->height(48),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable(),
                Tables\Columns\TextColumn::make('description_1')
                    ->label('Deskripsi 1')
                    ->limit(60)
                    ->tooltip(fn (MasterTraining $record): ?string => $record->description_1)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('description_2')
                    ->label('Deskripsi 2')
                    ->limit(60)
                    ->tooltip(fn (MasterTraining $record): ?string => $record->description_2)
                    ->toggleable(),
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
            'index' => Pages\ManageMasterTrainings::route('/'),
        ];
    }
}
