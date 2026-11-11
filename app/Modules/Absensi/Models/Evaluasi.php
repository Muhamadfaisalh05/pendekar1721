<?php

namespace App\Modules\Absensi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nilai evaluasi harian (tabel `evaluasi`, parity dengan aplikasi native SEMARAK).
 *
 * Nilai `libur` dipakai untuk menandai anak sakit/izin pada seluruh kegiatan
 * di tanggal tersebut (native tidak punya tabel izin terpisah).
 *
 * @property int $id
 * @property string $anak_id
 * @property int $kegiatan_id
 * @property \Illuminate\Support\Carbon $tanggal
 * @property string $nilai
 */
class Evaluasi extends Model
{
    public const NILAI_SESUAI = 'sesuai';
    public const NILAI_ARAHAN = 'arahan';
    public const NILAI_TELAT = 'telat';
    public const NILAI_TIDAK = 'tidak';
    public const NILAI_LIBUR = 'libur';

    /**
     * Urutan nilai sesuai tampilan native.
     *
     * @var list<string>
     */
    public const NILAI = [
        self::NILAI_SESUAI,
        self::NILAI_ARAHAN,
        self::NILAI_TELAT,
        self::NILAI_TIDAK,
        self::NILAI_LIBUR,
    ];

    /**
     * Nilai yang dihitung dalam skor (native mengecualikan `libur`).
     *
     * @var list<string>
     */
    public const NILAI_DIHITUNG = [
        self::NILAI_SESUAI,
        self::NILAI_ARAHAN,
        self::NILAI_TELAT,
        self::NILAI_TIDAK,
    ];

    protected $connection = 'semarak';

    protected $table = 'evaluasi';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'anak_id',
        'kegiatan_id',
        'tanggal',
        'nilai',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kegiatan_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Anak, $this>
     */
    public function anak(): BelongsTo
    {
        return $this->belongsTo(Anak::class, 'anak_id', 'id');
    }

    /**
     * @return BelongsTo<Kegiatan, $this>
     */
    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'kegiatan_id', 'id');
    }
}
