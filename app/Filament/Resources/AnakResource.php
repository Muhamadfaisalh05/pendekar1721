<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AnakResource\Pages;
use App\Modules\Absensi\Models\Anak;
use App\Modules\Absensi\Services\AnakService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * CRUD anak binaan modul absensi (database `semarak`).
 * Tidak ada di aplikasi native (native hanya punya halaman form + API).
 */
class AnakResource extends Resource
{
    protected static ?string $model = Anak::class;

    protected static ?string $modelLabel = 'Anak Binaan';

    protected static ?string $pluralModelLabel = 'Anak Binaan';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Absensi';

    protected static ?string $slug = 'anak';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('id')
                    ->label('ID Anak')
                    ->required()
                    ->maxLength(20)
                    ->default(fn (): string => app(AnakService::class)->generateId())
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated()
                    ->helperText('Format AN-001. Dapat dikosongkan? Tidak — ID wajib.'),

                Forms\Components\TextInput::make('nama')
                    ->required()
                    ->maxLength(150),

                Forms\Components\Select::make('jenis_kelamin')
                    ->options([
                        'Laki-laki' => 'Laki-laki',
                        'Perempuan' => 'Perempuan',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('asal_daerah')
                    ->label('Asal Daerah')
                    ->required()
                    ->maxLength(100),

                Forms\Components\TextInput::make('agama')
                    ->required()
                    ->maxLength(50),

                Forms\Components\TextInput::make('umur')
                    ->numeric()
                    ->minValue(1)
                    ->required(),

                Forms\Components\TextInput::make('angkatan')
                    ->required()
                    ->maxLength(50),

                Forms\Components\Select::make('status')
                    ->options([
                        Anak::STATUS_AKTIF => Anak::STATUS_AKTIF,
                        Anak::STATUS_NON_AKTIF => Anak::STATUS_NON_AKTIF,
                        Anak::STATUS_LULUS => Anak::STATUS_LULUS,
                    ])
                    ->default(Anak::STATUS_AKTIF)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nama')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable(isIndividual: true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('nama')
                    ->searchable(isIndividual: true, isGlobal: true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('jenis_kelamin')
                    ->label('JK')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('asal_daerah')
                    ->label('Asal Daerah')
                    ->searchable(isIndividual: true)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('umur')
                    ->sortable(),

                Tables\Columns\TextColumn::make('angkatan')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Anak::STATUS_AKTIF => 'success',
                        Anak::STATUS_LULUS => 'info',
                        default => 'danger',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('angkatan')
                    ->options(fn (): array => Anak::query()
                        ->distinct()
                        ->orderBy('angkatan')
                        ->pluck('angkatan', 'angkatan')
                        ->all()),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Anak::STATUS_AKTIF => Anak::STATUS_AKTIF,
                        Anak::STATUS_NON_AKTIF => Anak::STATUS_NON_AKTIF,
                        Anak::STATUS_LULUS => Anak::STATUS_LULUS,
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Menghapus anak juga menghapus seluruh nilai evaluasinya.'),
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
            'index' => Pages\ManageAnak::route('/'),
        ];
    }
}
