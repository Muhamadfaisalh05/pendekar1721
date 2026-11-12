<?php

namespace App\Support\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Relasi `HasOne` yang SELALU kosong.
 *
 * Dipakai saat kolom penghubungnya belum ada di skema, misalnya migrasi
 * `users.anak_id` (Fase 2) belum dijalankan. Relasi HasOne biasa selalu
 * menambahkan constraint `users.anak_id = ?`, sehingga query langsung gagal
 * dengan "no such column: users.anak_id" (error 500). Kedua method constraint
 * di bawah diganti `1 = 0` sehingga query tidak pernah menyebut kolom yang
 * tidak ada dan hasilnya selalu null/kosong.
 */
final class RelasiHasOneKosong extends HasOne
{
    public function addConstraints(): void
    {
        $this->query->whereRaw('1 = 0');
    }

    /**
     * @param  array<int, Model>  $models
     */
    public function addEagerConstraints(array $models): void
    {
        $this->query->whereRaw('1 = 0');
    }
}
