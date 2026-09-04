<?php

namespace App\Filament\Resources;

use App\Enums\UserStatus;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        $isCreate = $form->getOperation() === "create";

        return $form
            ->schema([
                Forms\Components\Grid::make(['default' => 6])
                    ->schema([
                        Forms\Components\Grid::make()
                            ->columnSpan(4)
                            ->schema([
                                Section::make('Data Utama')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('email')
                                            ->required()
                                            ->email()
                                            ->maxLength(255),
                                        Forms\Components\Grid::make(['default' => 2])
                                            ->schema([
                                                Forms\Components\TextInput::make('password')
                                                    ->label('Password')
                                                    ->required($isCreate)
                                                    ->confirmed()
                                                    ->password()
                                                    ->revealable(),
                                                Forms\Components\TextInput::make('password_confirmation')
                                                    ->label('Ulangi Password')
                                                    ->required($isCreate)
                                                    ->password()
                                                    ->revealable(),
                                            ]),
                                        Forms\Components\Grid::make(['default' => 2])
                                            ->schema([
                                                Forms\Components\Select::make('user_type')
                                                    ->label('Jenis User')
                                                    ->options([
                                                        'admin' => 'Administrator',
                                                        'client' => 'Klien',
                                                    ])
                                                    ->required()
                                                    ->live(),
                                                Forms\Components\Select::make('status')
                                                    ->label('Status')
                                                    ->options(UserStatus::class)
                                                    ->required(),
                                            ]),
                                    ]),
                                Section::make('Data Profil')
                                    ->visible(fn (Get $get) => $get('user_type') === 'client')
                                    ->hiddenOn('create')
                                    ->relationship('userProfile')
                                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data) {
                                        $data['birth_date'] = Carbon::createFromDate(year: $data['birth_date_year'], month: $data['birth_date_month'], day: $data['birth_date_day']);

                                        unset($data['birth_date_day']);
                                        unset($data['birth_date_month']);
                                        unset($data['birth_date_year']);

                                        return $data;
                                    })
                                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data) {
                                        $data['birth_date'] = Carbon::createFromDate(year: $data['birth_date_year'], month: $data['birth_date_month'], day: $data['birth_date_day']);

                                        unset($data['birth_date_day']);
                                        unset($data['birth_date_month']);
                                        unset($data['birth_date_year']);

                                        return $data;
                                    })
                                    ->schema([
                                        Forms\Components\TextInput::make('description')
                                            ->label('Deskripsi Singkat')
                                            ->required()
                                            ->maxLength(400),
                                        Forms\Components\Grid::make(['default' => 3])
                                            ->schema([
                                                Forms\Components\TextInput::make('birth_place')
                                                    ->label('Tempat Lahir')
                                                    ->required()
                                                    ->maxLength(255),
                                                Forms\Components\Grid::make(['default' => 3])
                                                    ->columnSpan(2)
                                                    ->schema([
                                                        Forms\Components\Select::make('birth_date_day')
                                                            ->label('Tanggal Lahir')
                                                            ->options(function () {
                                                                $day = [];
                                                                for ($n = 1; $n <= 31; $n++) {
                                                                    $day[$n] = $n;
                                                                }
                                                                return $day;
                                                            })
                                                            ->required(),
                                                        Forms\Components\Select::make('birth_date_month')
                                                            ->label('Bulan')
                                                            ->options(function () {
                                                                return [
                                                                    1 => 'Januari',
                                                                    2 => 'Februari',
                                                                    3 => 'Maret',
                                                                    4 => 'April',
                                                                    5 => 'Mei',
                                                                    6 => 'Juni',
                                                                    7 => 'Juli',
                                                                    8 => 'Agustus',
                                                                    9 => 'September',
                                                                    10 => 'Oktober',
                                                                    11 => 'November',
                                                                    12 => 'Desember',
                                                                ];
                                                            })
                                                            ->required(),
                                                        Forms\Components\Select::make('birth_date_year')
                                                            ->label('Tahun')
                                                            ->options(function () {
                                                                $day = [];
                                                                for ($n = 1990; $n <= now()->year - 14; $n++) {
                                                                    $day[$n] = $n;
                                                                }
                                                                return $day;
                                                            })
                                                            ->required(),
                                                    ]),
                                            ]),
                                        Forms\Components\Grid::make(['default' => 3])
                                            ->schema([
                                                Forms\Components\Select::make('gender')
                                                    ->label('Jenis Kelamin')
                                                    ->options([
                                                        'L' => 'Laki-Laki',
                                                        'P' => 'Perempuan',
                                                    ])
                                                    ->required(),
                                                Forms\Components\TextInput::make('body_weight')
                                                    ->label('Berat Badan (kg)')
                                                    ->numeric()
                                                    ->required()
                                                    ->maxLength(255),
                                                Forms\Components\TextInput::make('body_height')
                                                    ->label('Tinggi Badan (cm)')
                                                    ->numeric()
                                                    ->required()
                                                    ->maxLength(255),
                                            ]),
                                        Forms\Components\Grid::make(['default' => 3])
                                            ->schema([
                                                Forms\Components\TextInput::make('ktp_address')
                                                    ->label('Alamat Sesuai KTP')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(2),
                                                Forms\Components\Select::make('ktp_city_id')
                                                    ->label('Kota Sesuai KTP')
                                                    ->relationship('ktpCity', 'title')
                                                    ->required(),
                                            ]),
                                        Forms\Components\Grid::make(['default' => 3])
                                            ->schema([
                                                Forms\Components\TextInput::make('domisili_address')
                                                    ->label('Alamat Tempat Tinggal Sesuai Domisili')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(2),
                                                Forms\Components\Select::make('domisili_city_id')
                                                    ->label('Kota Tempat Tinggal Domisili')
                                                    ->relationship('ktpCity', 'title')
                                                    ->required(),
                                            ]),
                                        Forms\Components\Grid::make(['default' => 3])
                                            ->schema([
                                                Forms\Components\Select::make('education_degree_id')
                                                    ->label('Jenjang Pendidikan')
                                                    ->relationship('educationDegree', 'title')
                                                    ->required(),
                                                Forms\Components\Select::make('religion_id')
                                                    ->label('Agama')
                                                    ->relationship('religion', 'title')
                                                    ->required(),
                                                Forms\Components\Select::make('ethnic_group_id')
                                                    ->label('Suku Asal')
                                                    ->relationship('ethnicGroup', 'title')
                                                    ->required(),
                                            ]),
                                        Forms\Components\Checkbox::make('skck_status')
                                            ->label('Memiliki SKCK'),
                                        Forms\Components\Checkbox::make('surat_kesehatan_status')
                                            ->label('Memiliki Surat Keterangan Sehat'),
                                    ]),
                                Section::make('Data Pengalaman Bekerja')
                                    ->hiddenOn('create')
                                    ->visible(fn (Get $get) => $get('user_type') === 'client')
                                    ->schema([
                                        Forms\Components\Repeater::make('work_experiences')
                                            ->relationship('userExperiences')
                                            ->label('Pengalaman Bekerja')
                                            ->addActionLabel('Tambahkan Pengalaman Bekerja')
                                            ->reorderableWithButtons()
                                            ->reorderableWithDragAndDrop(false)
                                            ->orderColumn('sort_order')
                                            ->schema([
                                                Grid::make(['default' => 1, 'xl' => 3])
                                                    ->schema([
                                                        Forms\Components\TextInput::make('experience_year')
                                                            ->label('Tahun Bekerja')
                                                            ->required(),
                                                        Forms\Components\TextInput::make('experience_job_name')
                                                            ->label('Nama Profesi, Jabatan, atau Posisi')
                                                            ->required(),
                                                        Forms\Components\TextInput::make('experience_description')
                                                            ->label('Nama Tempat Bekerja')
                                                            ->required(),
                                                        Checkbox::make('is_current_job')
                                                            ->label('Sedang bekerja di sini?')
                                                            ->fixIndistinctState(function (Get $get) {
                                                                return $get('is_current_job') === true;
                                                            }),
                                                    ])
                                            ])
                                    ])
                            ]),
                        Section::make('Data Lainnya')
                            ->columnSpan(2)
                            ->hiddenOn('create')
                            ->visible(fn (Get $get) => $get('user_type') === 'client')
                            ->relationship('userProfile')
                            ->schema([
                                Forms\Components\FileUpload::make('profile_picture_path')
                                    ->label('Foto Profil')
                                    ->required()
                                    // ->disk('public')
                                    ->directory('profile-picture')
                                    ->visibility('public')
                                    ->image()
                                    ->imageResizeMode('cover')
                                    ->imageCropAspectRatio('3:4')
                                    // ->panelAspectRatio('3:4')
                                    ->imagePreviewHeight('450')
                                    ->imageEditor()
                                    ->imageEditorAspectRatios([
                                        '3:4',
                                    ])
                                    ->imageResizeTargetWidth('675')
                                    ->imageResizeTargetHeight('900')
                                    ->openable()
                                    ->downloadable(),
                            ])
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_type')
                    ->label('Jenis User')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('userProfile.hire_status')
                    ->label('Tersedia Kerja')
                    ->sortable()
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers\UserSkillsRelationManager::class,
            RelationManagers\UserTrainingsRelationManager::class,
            RelationManagers\UserWorkLocationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
