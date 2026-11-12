<?php

namespace App\Modules\Absensi\Models;

use App\Models\User;
use App\Support\Relations\RelasiHasOneKosong;
use App\Support\SchemaGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /**
     * Akun klien aplikasi yang tertaut ke anak ini (FASE 3).
     *
     * Relasi lintas database: `anak` ada di koneksi `semarak`, `users` di
     * koneksi aplikasi. Eloquent tetap bisa menjalankan relasi ini karena
     * query-nya dijalankan di koneksi model terkait (tanpa JOIN lintas
     * database), sehingga aman untuk MySQL maupun SQLite.
     *
     * Dibangun manual (bukan `$this->hasOne()`): `Model::newRelatedInstance()`
     * menyalin nama koneksi parent (`semarak`) ke model terkait bila model itu
     * tidak punya `$connection` eksplisit, sehingga query akan mencari tabel
     * `users` di database SEMARAK dan gagal.
     *
     * PENGAMAN SKEMA: bila migrasi Fase 2 (`users.anak_id`) belum dijalankan,
     * kolom penghubung belum ada sehingga relasi biasa akan gagal dengan
     * "no such column". Dalam kondisi itu dikembalikan relasi kosong — aman
     * dipanggil/di-eager-load dan hasilnya selalu null.
     *
     * @return HasOne<User, $this>
     */
    public function akunKlien(): HasOne
    {
        $akun = new User;

        if (! SchemaGuard::usersPunyaTautanAnak()) {
            return new RelasiHasOneKosong(
                $akun->newQuery(),
                $this,
                $akun->qualifyColumn('anak_id'),
                $this->getKeyName(),
            );
        }

        return new HasOne(
            $akun->newQuery(),
            $this,
            $akun->qualifyColumn('anak_id'),
            $this->getKeyName(),
        );
    }
}
