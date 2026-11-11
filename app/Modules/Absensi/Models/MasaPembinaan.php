<?php

namespace App\Modules\Absensi\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Masa pembinaan per angkatan (tabel `masa_pembinaan`, parity dengan native SEMARAK).
 *
 * @property string $angkatan
 * @property \Illuminate\Support\Carbon|null $tanggal_selesai
 */
class MasaPembinaan extends Model
{
    protected $connection = 'semarak';

    protected $table = 'masa_pembinaan';

    protected $primaryKey = 'angkatan';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'angkatan',
        'tanggal_selesai',
    ];
}
