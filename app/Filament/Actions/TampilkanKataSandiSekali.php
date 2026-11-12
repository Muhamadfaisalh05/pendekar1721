<?php

namespace App\Filament\Actions;

use Filament\Tables\Actions\Action;

/**
 * Modal "kata sandi sementara" yang hanya bisa dilihat SEKALI.
 *
 * Aksi ini tidak punya tombol sendiri di tabel (tersembunyi). Ia dipasang
 * secara programatik setelah aksi yang menghasilkan kata sandi selesai:
 *
 *     $action->getLivewire()->replaceMountedTableAction(
 *         'kataSandiSekali',
 *         (string) $record->getKey(),
 *         ['nama' => $record->nama, 'password' => $hasil['password']],
 *     );
 *
 * Plaintext hanya hidup di argumen aksi (memori request) — tidak pernah
 * disimpan di database maupun log — sehingga hilang saat modal ditutup atau
 * halaman dimuat ulang.
 *
 * Gunakan nama aksi yang sama di kedua sisi:
 * `TampilkanKataSandiSekali::make('kataSandiSekali')`.
 */
class TampilkanKataSandiSekali extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            // Tersembunyi selama tidak ada kata sandi di argumen; baru muncul
            // saat dipasang lewat `replaceMountedTableAction(..., ['password' => ...])`.
            // Sengaja TIDAK memakai `->hidden()` statis: Filament menandai aksi
            // tersembunyi sebagai `isDisabled()`, sehingga aksi tidak bisa
            // dipasang secara programatik.
            ->hidden(fn (array $arguments): bool => blank($arguments['password'] ?? null))
            ->modalHeading('Kata Sandi Sementara')
            ->modalDescription('Catat sekarang. Kata sandi hanya ditampilkan sekali dan tidak dapat dilihat lagi.')
            ->modalContent(fn (array $arguments) => view('filament.actions.kata-sandi-sekali', [
                'nama' => $arguments['nama'] ?? null,
                'password' => $arguments['password'] ?? '',
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Sudah dicatat')
            ->action(static function (): void {
                // Tidak ada yang perlu disimpan: kata sandi sudah tersimpan
                // sebagai hash oleh service sebelum aksi ini dipasang.
            });
    }
}
