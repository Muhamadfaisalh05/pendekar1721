<?php

namespace App\Filament\Client\Resources;

use App\Filament\Client\Resources\AlumniResource\Pages;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Direktori alumni untuk panel Klien — READ-ONLY dan OPSIONAL.
 *
 * Hanya aktif bila `config('pendekar.alumni.directory_enabled')` bernilai true
 * (default false, lihat D-02). Daftar sengaja TIDAK memuat email, telepon,
 * atau alamat: hanya nama, jenis kelamin, pendidikan, dan pelatihan.
 */
class AlumniResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Alumni';

    protected static ?string $pluralModelLabel = 'Data Alumni';

    protected static ?string $slug = 'alumni';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 5;

    public static function isDirectoryEnabled(): bool
    {
        return (bool) config('pendekar.alumni.directory_enabled', false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isDirectoryEnabled() && parent::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * Definisi alumni mengikuti `User::scopeAlumni()` (FASE 2):
     * `user_type = client` AND `status = published` AND `graduated_at IS NOT NULL`.
     *
     * Direktori akan kosong sampai backfill `graduated_at` dijalankan setelah
     * audit produksi — perilaku ini disengaja agar tidak ada data orang yang
     * salah tampil sebagai alumni.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->alumni();
    }

    public static function table(Table $table): Table
    {
        return $table
            // Direktori alumni dapat berisi banyak baris; muat barisnya
            // menyusul agar navigasi panel Klien tetap ringan.
            ->deferLoading()
            ->columns([
                Tables\Columns\ImageColumn::make('userProfile.profile_picture_path')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png')),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('userProfile.gender')
                    ->label('L/P')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'L' => 'info',
                        'P' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('userProfile.educationDegree.title')
                    ->label('Pendidikan')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('userTrainings.title')
                    ->label('Pelatihan')
                    ->badge()
                    ->placeholder('-'),
            ])
            ->filters([
                //
            ])
            ->actions([
                // Read-only: tanpa aksi.
            ])
            ->bulkActions([
                // Read-only: tanpa aksi massal.
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAlumnis::route('/'),
        ];
    }
}
