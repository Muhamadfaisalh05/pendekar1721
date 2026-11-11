<?php

namespace App\Modules\Absensi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Kegiatan harian (tabel `kegiatan`, parity dengan aplikasi native SEMARAK).
 *
 * @property int $id
 * @property string $nama
 */
class Kegiatan extends Model
{
    protected $connection = 'semarak';

    protected $table = 'kegiatan';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nama',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
        ];
    }

    /**
     * @return HasMany<Evaluasi, $this>
     */
    public function evaluasi(): HasMany
    {
        return $this->hasMany(Evaluasi::class, 'kegiatan_id', 'id');
    }

    /**
     * @return HasOne<JadwalKegiatan, $this>
     */
    public function jadwal(): HasOne
    {
        return $this->hasOne(JadwalKegiatan::class, 'kegiatan_id', 'id');
    }
}
