<?php

namespace App\Filament\Resources;

use App\Enums\UserStatus;
use App\Filament\Actions\TampilkanKataSandiSekali;
use App\Filament\Resources\AnakResource\Pages;
use App\Models\User;
use App\Modules\Absensi\Models\Anak;
use App\Modules\Absensi\Services\AnakService;
use App\Services\GraduationService;
use App\Support\SchemaGuard;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * CRUD anak binaan modul absensi (database `semarak`).
 * Tidak ada di aplikasi native (native hanya punya halaman form + API).
 *
 * FASE 3 menambahkan dua hal:
 * - kolom "Akun Klien" (status akun aplikasi yang tertaut lewat `users.anak_id`);
 * - aksi "Luluskan & Aktifkan Akun Klien" yang menjalankan GraduationService
 *   lalu menampilkan kata sandi sementara SATU kali lewat modal.
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
        // PENGAMAN SKEMA: kolom "Akun Klien" dan eager load-nya hanya dipasang
        // bila migrasi Fase 2 (`users.anak_id`) sudah dijalankan. Tanpa kolom
        // itu, relasi `akunKlien` selalu kosong dan query eager load akan gagal.
        $punyaTautanAnak = SchemaGuard::usersPunyaTautanAnak();

        $kolomAkunKlien = Tables\Columns\TextColumn::make('akun_klien')
            ->label('Akun Klien')
            ->badge()
            ->state(fn (Anak $record): string => static::labelAkunKlien($record))
            ->color(fn (Anak $record): string => static::warnaAkunKlien($record))
            ->description(fn (Anak $record): ?string => $record->akunKlien?->email)
            ->tooltip(fn (Anak $record): ?string => $record->akunKlien === null
                ? 'Belum ada akun klien tertaut. Gunakan aksi "Luluskan & Aktifkan Akun Klien".'
                : 'Akun #'.$record->akunKlien->getKey().' — '.static::labelAkunKlien($record));

        // Alamat KTP diambil dari profil akun klien yang tertaut. Nama anak
        // sekarang boleh sama (identitas = nama + alamat KTP + tanggal lahir +
        // asal + angkatan), sehingga alamat menjadi pembeda utama di tabel.
        $kolomAlamat = Tables\Columns\TextColumn::make('alamat_ktp')
            ->label('Alamat (KTP)')
            ->state(fn (Anak $record): ?string => static::alamatKtp($record))
            ->placeholder('Belum ada')
            ->wrap()
            ->limit(80)
            ->toggleable()
            ->searchable(
                query: fn (Builder $query, string $search): Builder => $query->whereIn(
                    'id',
                    static::idAnakDenganAlamat($search),
                ),
                isIndividual: true,
                isGlobal: true,
            );

        return $table
            // Tabel dimuat menyusul (satu request Livewire) supaya kerangka
            // halaman dan menu tampil lebih dulu. Ini terutama terasa saat
            // pindah halaman, karena tabel + aksi per baris adalah bagian
            // HTML terbesar pada halaman ini.
            ->deferLoading()
            // Eager load akun klien + profilnya lintas koneksi: satu query
            // tambahan untuk seluruh halaman tabel, bukan satu query per baris
            // (N+1) saat menampilkan alamat KTP.
            ->modifyQueryUsing(fn (Builder $query): Builder => $punyaTautanAnak
                ? $query->with('akunKlien.userProfile')
                : $query)
            ->defaultSort('nama')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable(isIndividual: true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('nama')
                    ->searchable(isIndividual: true, isGlobal: true)
                    ->sortable(),

                // Kolom sekunder disembunyikan default agar tabel ringkas dan
                // HTML per baris lebih kecil; operator tetap bisa
                // menampilkannya lewat menu pemilih kolom.
                Tables\Columns\TextColumn::make('jenis_kelamin')
                    ->label('JK')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('asal_daerah')
                    ->label('Asal Daerah')
                    ->searchable(isIndividual: true)
                    ->toggleable(isToggledHiddenByDefault: true),

                // Alamat KTP dari profil akun klien (hanya bila `users.anak_id`
                // sudah dimigrasikan, karena sumbernya relasi tersebut).
                ...($punyaTautanAnak ? [$kolomAlamat] : []),

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

                // Kolom akun klien hanya ada bila kolom `users.anak_id` sudah
                // dimigrasikan (lihat $punyaTautanAnak di atas).
                ...($punyaTautanAnak ? [$kolomAkunKlien] : []),
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

                // Saringan ini dipakai tautan "Anak Tanpa Akun Klien" pada
                // dashboard Admin. Hanya dipasang bila kolom tautan akun
                // (users.anak_id) sudah dimigrasikan; tanpa kolom itu query
                // akan gagal dengan "no such column".
                ...($punyaTautanAnak ? [
                    Tables\Filters\Filter::make('tanpa_akun')
                        ->label('Belum punya akun Klien')
                        ->query(fn (Builder $query): Builder => $query->whereNotIn(
                            'id',
                            User::query()->whereNotNull('anak_id')->pluck('anak_id')->all(),
                        )),
                ] : []),
            ])
            ->actions([
                Tables\Actions\Action::make('luluskan')
                    ->label('Luluskan & Aktifkan Akun Klien')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->visible(fn (Anak $record): bool => static::bolehLuluskan($record))
                    // PENGAMAN SKEMA: bila migrasi Fase 2 belum dijalankan,
                    // aksi dinonaktifkan (tetap terlihat) dengan tooltip
                    // penjelas agar operator tahu penyebabnya.
                    ->disabled(fn (): bool => ! SchemaGuard::usersPunyaKolomAlumni())
                    ->tooltip(fn (): ?string => SchemaGuard::usersPunyaKolomAlumni()
                        ? null
                        : 'Jalankan migrasi database terlebih dahulu')
                    ->modalHeading(fn (Anak $record): string => 'Luluskan '.$record->nama)
                    ->modalDescription(fn (Anak $record): string => static::deskripsiKelulusan($record))
                    ->modalSubmitActionLabel('Luluskan & Aktifkan')
                    ->form([
                        // Identitas lengkap: nama anak boleh sama, jadi alamat
                        // KTP + angkatan ikut ditampilkan sebelum akun diaktifkan.
                        Forms\Components\Placeholder::make('identitas_anak')
                            ->label('Identitas Anak')
                            ->content(fn (Anak $record): string => static::ringkasanIdentitas($record)),

                        Forms\Components\TextInput::make('email')
                            ->label('Email Akun Klien')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->default(fn (Anak $record): ?string => $record->akunKlien?->email)
                            ->helperText('Dipakai untuk login setelah akun diaktifkan.'),

                        Forms\Components\DatePicker::make('tanggal_lulus')
                            ->label('Tanggal Lulus')
                            ->maxDate(now())
                            ->helperText('Boleh dikosongkan — default hari ini.'),
                    ])
                    ->action(function (Anak $record, array $data, Tables\Actions\Action $action): void {
                        try {
                            $hasil = app(GraduationService::class)->luluskan(
                                $record,
                                (string) ($data['email'] ?? ''),
                                $data['tanggal_lulus'] ?? null,
                                Auth::id(),
                            );
                        } catch (InvalidArgumentException $e) {
                            Notification::make()
                                ->title('Kelulusan gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            // Modal tetap terbuka supaya email bisa diperbaiki
                            // tanpa mengulang dari awal. halt() melempar Halt.
                            $action->halt();

                            return;
                        }

                        Notification::make()
                            ->title('Akun klien aktif')
                            ->body($hasil['akun_dibuat']
                                ? "Akun klien untuk {$record->nama} dibuat dan diaktifkan."
                                : "Akun klien yang sudah tertaut untuk {$record->nama} diaktifkan.")
                            ->success()
                            ->send();

                        // Kata sandi hanya ada di argumen modal ini (memori
                        // request), tidak tersimpan di DB maupun log.
                        if (filled($hasil['password'] ?? null)) {
                            $action->getLivewire()->replaceMountedTableAction(
                                'kataSandiSekali',
                                (string) $record->getKey(),
                                [
                                    'nama' => $record->nama,
                                    'password' => $hasil['password'],
                                ],
                            );
                        }
                    }),

                TampilkanKataSandiSekali::make('kataSandiSekali'),

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

    /**
     * Aksi kelulusan hanya untuk Administrator Pendekar dan hanya untuk anak
     * yang belum berstatus Lulus (kelulusan ulang tetap bisa lewat service,
     * tetapi tidak disediakan tombol agar tidak terklik dua kali).
     */
    public static function bolehLuluskan(Anak $anak, ?User $aktor = null): bool
    {
        $aktor ??= Auth::user();

        return $aktor !== null
            && $aktor->isAdmin()
            && $anak->status !== Anak::STATUS_LULUS;
    }

    /**
     * Alamat KTP dari profil akun klien yang tertaut ke anak.
     *
     * Anak sendiri tidak menyimpan alamat; sumbernya `users.user_profiles`.
     * Null bila migrasi Fase 2 belum ada atau anak belum punya akun.
     */
    public static function alamatKtp(Anak $anak): ?string
    {
        if (! SchemaGuard::usersPunyaTautanAnak()) {
            return null;
        }

        $alamat = trim((string) ($anak->akunKlien?->userProfile?->ktp_address ?? ''));

        return $alamat === '' ? null : $alamat;
    }

    /**
     * ID anak yang alamat KTP akun tertautnya mengandung kata kunci.
     *
     * Dipakai kolom Alamat untuk pencarian tabel. Sengaja memakai query
     * terpisah (bukan `whereHas`) karena `users` dan `anak` berada di koneksi
     * berbeda, sehingga subquery lintas koneksi tidak dapat diandalkan.
     *
     * @return array<int, string>
     */
    protected static function idAnakDenganAlamat(string $search): array
    {
        $kata = trim($search);

        if ($kata === '' || ! SchemaGuard::usersPunyaTautanAnak()) {
            return [];
        }

        return User::query()
            ->whereNotNull('anak_id')
            ->whereHas('userProfile', fn (Builder $query): Builder => $query
                ->where('ktp_address', 'like', '%'.$kata.'%'))
            ->pluck('anak_id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    /**
     * Ringkasan identitas anak untuk modal kelulusan: nama, angkatan, dan
     * alamat KTP. Nama anak boleh sama, sehingga angkatan/alamat ikut
     * ditampilkan agar operator mengaktifkan akun orang yang benar.
     */
    public static function ringkasanIdentitas(Anak $anak): string
    {
        $alamat = static::alamatKtp($anak);

        return $anak->nama.' · '.$anak->angkatan.' · '.($alamat ?? 'alamat KTP belum ada');
    }

    /**
     * Penjelasan singkat keadaan akun pada modal kelulusan.
     */
    public static function deskripsiKelulusan(Anak $anak): string
    {
        $akun = $anak->akunKlien;

        if ($akun === null) {
            return 'Anak ini belum punya akun klien. Akun dibuat (Draft) lalu langsung '
                .'diaktifkan sebagai alumni, dan kata sandi sementara ditampilkan satu kali.';
        }

        $status = $akun->status?->getLabel() ?? 'tanpa status';

        return "Akun klien #{$akun->getKey()} ({$status}) akan diaktifkan sebagai alumni. "
            .'Kata sandi hanya diganti bila akun belum pernah punya kata sandi.';
    }

    /**
     * Label kolom "Akun Klien".
     */
    public static function labelAkunKlien(Anak $anak): string
    {
        $akun = $anak->akunKlien;

        return match (true) {
            $akun === null => 'Belum ada',
            $akun->status === UserStatus::Published => 'Aktif',
            $akun->status === UserStatus::Draft => 'Draft',
            $akun->status === UserStatus::Archived => 'Diarsipkan',
            default => 'Tanpa status',
        };
    }

    /**
     * Warna badge kolom "Akun Klien".
     */
    public static function warnaAkunKlien(Anak $anak): string
    {
        $akun = $anak->akunKlien;

        return match (true) {
            $akun === null => 'gray',
            $akun->status === UserStatus::Published => 'success',
            $akun->status === UserStatus::Draft => 'warning',
            default => 'danger',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAnak::route('/'),
        ];
    }
}
