<?php

namespace App\Modules\Absensi\Services;

use App\Modules\Absensi\Exceptions\ApiException;
use App\Modules\Absensi\Models\Anak;
use App\Modules\Absensi\Models\Evaluasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CRUD anak binaan — parity dengan `semarak_store_anak`,
 * `semarak_update_anak`, `semarak_delete_anak` di aplikasi native.
 */
class AnakService
{
    /**
     * Validasi & normalisasi payload anak (native: semarak_normalize_anak_payload).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalize(array $payload): array
    {
        $nama = trim((string) ($payload['nama'] ?? ''));
        if ($nama === '') {
            throw new ApiException('Nama anak wajib diisi.');
        }

        $jenisKelamin = trim((string) ($payload['jenis_kelamin'] ?? ''));
        if ($jenisKelamin === '') {
            throw new ApiException('Jenis kelamin wajib diisi.');
        }

        $asalDaerah = trim((string) ($payload['asal_daerah'] ?? ''));
        if ($asalDaerah === '') {
            throw new ApiException('Asal daerah wajib diisi.');
        }

        $agama = trim((string) ($payload['agama'] ?? ''));
        if ($agama === '') {
            throw new ApiException('Agama wajib diisi.');
        }

        $umur = (int) ($payload['umur'] ?? 0);
        if ($umur <= 0) {
            throw new ApiException('Umur wajib lebih dari 0.');
        }

        $angkatan = trim((string) ($payload['angkatan'] ?? ''));
        if ($angkatan === '') {
            throw new ApiException('Angkatan wajib diisi.');
        }

        $status = trim((string) ($payload['status'] ?? Anak::STATUS_AKTIF));
        if ($status === '') {
            $status = Anak::STATUS_AKTIF;
        }

        return [
            'nama' => $nama,
            'jenis_kelamin' => $jenisKelamin,
            'asal_daerah' => $asalDaerah,
            'agama' => $agama,
            'umur' => $umur,
            'angkatan' => $angkatan,
            'status' => $status,
        ];
    }

    /**
     * ID otomatis format `AN-001` (native: semarak_generate_anak_id).
     */
    public function generateId(): string
    {
        $ids = Anak::query()
            ->where('id', 'like', 'AN-%')
            ->pluck('id')
            ->all();

        $max = 0;
        foreach ($ids as $id) {
            if (preg_match('/(\d+)$/', (string) $id, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'AN-'.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function store(array $payload): array
    {
        $data = $this->normalize($payload);
        $id = trim((string) ($payload['id'] ?? ''));

        if ($id === '') {
            $id = $this->generateId();
        } elseif (preg_match('/^AN-\d{3,}$/i', $id) !== 1) {
            throw new ApiException('Format ID anak tidak valid. Contoh: AN-001');
        }

        if (Anak::query()->whereKey($id)->exists()) {
            throw new ApiException('ID anak sudah ada. Gunakan ID lain atau biarkan sistem membuat otomatis.');
        }

        Anak::query()->create(['id' => $id] + $data);

        return [
            'success' => true,
            'message' => 'Data anak berhasil ditambahkan.',
            'data' => ['id' => $id] + $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(string $id, array $payload): array
    {
        $anak = Anak::query()->find($id);

        if ($anak === null) {
            throw new ApiException('Data anak tidak ditemukan.');
        }

        $data = $this->normalize($payload);
        $anak->fill($data)->save();

        return [
            'success' => true,
            'message' => 'Data anak berhasil diperbarui.',
            'data' => ['id' => $id] + $data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $id): array
    {
        $anak = Anak::query()->find($id);

        if ($anak === null) {
            throw new ApiException('Data anak tidak ditemukan.');
        }

        DB::connection('semarak')->transaction(function () use ($anak): void {
            // Native menghapus evaluasi lebih dulu, lalu anak (FK cascade juga aktif).
            Evaluasi::query()->where('anak_id', $anak->id)->delete();
            $anak->delete();
        });

        Log::info('absensi.anak.dihapus', ['id' => $id, 'oleh' => auth()->id()]);

        return [
            'success' => true,
            'message' => 'Data anak berhasil dihapus.',
            'id' => $id,
        ];
    }
}
