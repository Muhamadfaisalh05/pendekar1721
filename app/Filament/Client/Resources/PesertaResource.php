<?php

namespace App\Filament\Client\Resources;

use App\Filament\Client\Resources\PesertaResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class PesertaResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Peserta';
    protected static ?string $pluralModelLabel = 'Data Peserta';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        // Hanya menampilkan user_type = client
        return parent::getEloquentQuery()->where('user_type', 'client');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Akun')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Profil Lengkap')
                    ->relationship('userProfile')
                    ->schema([
                        Forms\Components\Select::make('gender')
                            ->label('Jenis Kelamin')
                            ->options([
                                'L' => 'Laki-Laki',
                                'P' => 'Perempuan',
                            ]),
                        Forms\Components\TextInput::make('birth_place')
                            ->label('Tempat Lahir')
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('birth_date')
                            ->label('Tanggal Lahir'),
                        Forms\Components\Select::make('ktp_city_id')
                            ->label('Kota KTP')
                            ->relationship('ktpCity', 'title')
                            ->searchable(),
                        Forms\Components\Select::make('education_degree_id')
                            ->label('Pendidikan')
                            ->relationship('educationDegree', 'title'),
                        Forms\Components\Textarea::make('ktp_address')
                            ->label('Alamat KTP')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('hire_status')
                            ->label('Status Ketersediaan')
                            ->options([
                                '1' => 'Tersedia',
                                '0' => 'Tidak Tersedia',
                            ])
                            ->default('1'),
                        Forms\Components\FileUpload::make('profile_picture_path')
                            ->label('Foto Profil')
                            ->image()
                            ->directory('profile_pictures')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Keterampilan & Penempatan')
                    ->schema([
                        Forms\Components\Select::make('userTrainings')
                            ->label('Pelatihan / Keterampilan')
                            ->relationship('userTrainings', 'title')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                        Forms\Components\Select::make('userWorkLocations')
                            ->label('Penempatan Wilayah')
                            ->relationship('userWorkLocations', 'title')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('userProfile.profile_picture_path')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png')),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('userProfile.gender')
                    ->label('L/P')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'L' => 'info',
                        'P' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('userProfile.educationDegree.title')
                    ->label('Pendidikan'),
                Tables\Columns\IconColumn::make('userProfile.hire_status')
                    ->label('Tersedia')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //
                ]),
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
            'index' => Pages\ListPesertas::route('/'),
            'create' => Pages\CreatePeserta::route('/create'),
            'edit' => Pages\EditPeserta::route('/{record}/edit'),
        ];
    }
}
