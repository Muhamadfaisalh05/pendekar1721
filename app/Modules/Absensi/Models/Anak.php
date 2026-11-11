<?php

namespace App\Modules\Absensi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Anak binaan (tabel `anak`, parity dengan aplikasi native SEMARAK).
 *
 * @property string $id
 * @property string $nama
 * @property string $jenis_kelamin
 * @property string $asal_daerah
 * @property string $agama
 * @property int $umur
 * @property string $angkatan
 * @property string $status
 */
class Anak extends Model
{
    public const STATUS_AKTIF = 'Aktif';
    public const STATUS_LULUS = 'Lulus';
    public const STATUS_NON_AKTIF = 'Non-Aktif';

    protected $connection = 'semarak';

    protected $table = 'anak';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'nama',
        'jenis_kelamin',
        'asal_daerah',
        'agama',
        'umur',
        'angkatan',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'umur' => 'integer',
        ];
    }

    /**
     * @return HasMany<Evaluasi, $this>
     */
    public function evaluasi(): HasMany
    {
        return $this->hasMany(Evaluasi::class, 'anak_id', 'id');
    }

    /**
     * @return HasMany<CatatanRapor, $this>
     */
    public function catatanRapor(): HasMany
    {
        return $this->hasMany(CatatanRapor::class, 'anak_id', 'id');
    }
}
