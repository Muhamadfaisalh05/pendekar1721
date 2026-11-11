<?php

namespace App\Modules\Absensi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan & rekomendasi rapor (tabel `catatan_rapor`, parity dengan native SEMARAK).
 *
 * @property int $id
 * @property string $anak_id
 * @property \Illuminate\Support\Carbon $periode_mulai
 * @property \Illuminate\Support\Carbon $periode_selesai
 * @property string $catatan
 * @property string $rekomendasi
 */
class CatatanRapor extends Model
{
    protected $connection = 'semarak';

    protected $table = 'catatan_rapor';

    public $timestamps = false;

    /**
     * Tanggal disimpan sebagai string `Y-m-d` (parity dengan native).
     *
     * @var list<string>
     */
    protected $fillable = [
        'anak_id',
        'periode_mulai',
        'periode_selesai',
        'catatan',
        'rekomendasi',
    ];

    /**
     * @return BelongsTo<Anak, $this>
     */
    public function anak(): BelongsTo
    {
        return $this->belongsTo(Anak::class, 'anak_id', 'id');
    }
}
