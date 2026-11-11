<?php

namespace App\Modules\Absensi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aturan jadwal per kegiatan (tabel `jadwal_kegiatan`, parity dengan native SEMARAK).
 *
 * @property int $kegiatan_id
 * @property string $aturan
 * @property array<int, string>|null $hari_json
 * @property array<int, array{mulai: string, selesai: string}>|null $rentang_json
 */
class JadwalKegiatan extends Model
{
    public const ATURAN_SETIAP_HARI = 'setiap_hari';
    public const ATURAN_HARI_KERJA = 'hari_kerja';
    public const ATURAN_HARI_TERTENTU = 'hari_tertentu';
    public const ATURAN_MULTI_RENTANG = 'multi_rentang';

    /**
     * @var list<string>
     */
    public const ATURAN = [
        self::ATURAN_SETIAP_HARI,
        self::ATURAN_HARI_KERJA,
        self::ATURAN_HARI_TERTENTU,
        self::ATURAN_MULTI_RENTANG,
    ];

    protected $connection = 'semarak';

    protected $table = 'jadwal_kegiatan';

    protected $primaryKey = 'kegiatan_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kegiatan_id',
        'aturan',
        'hari_json',
        'rentang_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kegiatan_id' => 'integer',
            'hari_json' => 'array',
            'rentang_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Kegiatan, $this>
     */
    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'kegiatan_id', 'id');
    }

    /**
     * Daftar hari (ISO 1=Senin ... 7=Minggu) sebagai string, seperti native.
     *
     * @return list<string>
     */
    public function hari(): array
    {
        return array_values(array_map(
            static fn ($day): string => (string) $day,
            (array) ($this->hari_json ?? [])
        ));
    }

    /**
     * Daftar rentang tanggal [{mulai, selesai}].
     *
     * @return list<array{mulai: string, selesai: string}>
     */
    public function rentang(): array
    {
        return array_values(array_map(
            static fn ($range): array => [
                'mulai' => (string) ($range['mulai'] ?? ''),
                'selesai' => (string) ($range['selesai'] ?? ''),
            ],
            (array) ($this->rentang_json ?? [])
        ));
    }
}
