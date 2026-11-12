<?php

namespace App\Filament\Resources;

use App\Enums\UserStatus;
use App\Filament\Actions\TampilkanKataSandiSekali;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use App\Services\UserAccountService;
use App\Support\SchemaGuard;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        $isCreate = $form->getOperation() === 'create';

        return $form
            ->schema([
                Grid::make(['default' => 6])
                    ->schema([
                        Grid::make()
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
                                        Grid::make(['default' => 2])
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
                                        Grid::make(['default' => 2])
                                            ->schema([
                                                Forms\Components\Select::make('user_type')
                                                    ->label('Jenis User')
                                                    ->options([
                                                        User::ROLE_ADMIN => 'Administrator Pendekar',
                                                        User::ROLE_ADMIN_ABSENSI => 'Admin Absensi (SEMARAK)',
                                                        User::ROLE_CLIENT => 'Klien',
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
                                        Grid::make(['default' => 3])
                                            ->schema([
                                                Forms\Components\TextInput::make('birth_place')
                                                    ->label('Tempat Lahir')
                                                    ->required()
                                                    ->maxLength(255),
                                                Grid::make(['default' => 3])
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
                                        Grid::make(['default' => 3])
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
                                        Grid::make(['default' => 3])
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
                                        Grid::make(['default' => 3])
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
                                        Grid::make(['default' => 3])
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
                                        Checkbox::make('skck_status')
                                            ->label('Memiliki SKCK'),
                                        Checkbox::make('surat_kesehatan_status')
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
                                                    ]),
                                            ]),
                                    ]),
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
                                    ->disk('public')
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
                            ]),
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
                    ->sortable(),
                // FASE 3: penautan ke `semarak.anak` dan penanda alumni.
                // Disembunyikan default agar tabel tetap ringkas. Kedua kolom
                // hanya dipasang bila migrasi Fase 2 sudah dijalankan, supaya
                // daftar user tetap terbuka saat migrasi tertunda.
                ...(SchemaGuard::usersPunyaTautanAnak() ? [
                    Tables\Columns\TextColumn::make('anak_id')
                        ->label('ID Anak (SEMARAK)')
                        ->placeholder('-')
                        ->toggleable(isToggledHiddenByDefault: true),
                ] : []),
                ...(SchemaGuard::usersPunyaTanggalLulus() ? [
                    Tables\Columns\TextColumn::make('graduated_at')
                        ->label('Tanggal Lulus')
                        ->dateTime('d M Y')
                        ->placeholder('-')
                        ->toggleable(isToggledHiddenByDefault: true),
                ] : []),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('resetPassword')
                    ->label('Reset Kata Sandi')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->visible(fn (): bool => (bool) Auth::user()?->isAdmin())
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => 'Reset kata sandi '.$record->name)
                    ->modalDescription(fn (User $record): string => static::deskripsiResetPassword($record))
                    ->modalSubmitActionLabel('Reset sekarang')
                    ->action(function (User $record, Tables\Actions\Action $action): void {
                        $plainPassword = app(UserAccountService::class)->resetPassword($record, Auth::id());

                        Notification::make()
                            ->title('Kata sandi direset')
                            ->body("Kata sandi baru untuk {$record->name} ditampilkan satu kali di jendela berikut.")
                            ->success()
                            ->send();

                        // Plaintext hanya hidup di argumen modal (memori request).
                        $action->getLivewire()->replaceMountedTableAction(
                            'kataSandiSekali',
                            (string) $record->getKey(),
                            [
                                'nama' => $record->name,
                                'password' => $plainPassword,
                            ],
                        );
                    }),

                TampilkanKataSandiSekali::make('kataSandiSekali'),

                Tables\Actions\DeleteAction::make()
                    ->label('Hapus')
                    ->requiresConfirmation()
                    ->modalDescription('Akun dihapus permanen. Riwayat anak binaan (bila ada) tetap tersimpan di SEMARAK.')
                    ->visible(fn (User $record): bool => app(UserAccountService::class)
                        ->bolehDihapus($record, Auth::user()))
                    ->action(function (User $record): void {
                        try {
                            app(UserAccountService::class)->hapus($record, Auth::user());
                        } catch (InvalidArgumentException $e) {
                            Notification::make()
                                ->title('Tidak dapat menghapus akun')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Akun dihapus')
                            ->body("Akun {$record->name} dihapus.")
                            ->success()
                            ->send();
                    }),
            ])
            // Hapus massal sengaja tidak disediakan: guard "tidak boleh hapus
            // diri sendiri / administrator terakhir" hanya bisa dijamin bila
            // penghapusan dilakukan satu per satu.
            ->bulkActions([]);
    }

    /**
     * Penjelasan modal Reset Kata Sandi.
     *
     * Akun Draft (binaan yang belum diluluskan) tetap bisa direset, tetapi
     * belum bisa login sampai statusnya diaktifkan lewat aksi kelulusan —
     * keterangan ini mencegah admin mengira reset password = aktivasi.
     */
    public static function deskripsiResetPassword(User $user): string
    {
        $status = $user->status?->getLabel() ?? 'tanpa status';

        $dasar = "Kata sandi baru dibuat acak (24 karakter) dan ditampilkan satu kali. Status akun: {$status}.";

        if ($user->status === UserStatus::Draft) {
            return $dasar.' Akun masih Draft sehingga belum bisa login — aktifkan lewat aksi '
                .'"Luluskan & Aktifkan Akun Klien" pada menu Anak Binaan.';
        }

        if ($user->status === UserStatus::Archived) {
            return $dasar.' Akun ini diarsipkan sehingga tetap tidak bisa login.';
        }

        return $dasar;
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
