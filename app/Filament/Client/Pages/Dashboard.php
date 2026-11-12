<?php

namespace App\Filament\Client\Pages;

use App\Models\User;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.client.pages.dashboard';

    protected static ?string $title = 'Dashboard Klien';

    public static function canAccess(): bool
    {
        return auth()->user()?->user_type === 'client';
    }

    /**
     * Statistik ringkas untuk dashboard klien.
     *
     * SECURITY (F-01): dashboard klien TIDAK menampilkan daftar nama/email
     * user lain. Semua angka di sini agregat atau milik user yang login.
     *
     * CATATAN DEFINISI (BELUM FINAL):
     * `total_alumni` masih memakai definisi sementara `user_type = client`
     * karena `graduated_at` belum diisi (backfill menunggu audit produksi,
     * lihat docs/AUDIT-PRODUKSI-USERS.md). Definisi resmi sudah tersedia di
     * `User::scopeAlumni()`; setelah backfill selesai, ganti baris di bawah
     * menjadi `User::query()->alumni()->count()` dan hapus penanda
     * `total_alumni_belum_final`.
     */
    public function getStatistik(): array
    {
        return [
            'total_alumni' => User::query()->where('user_type', 'client')->count(),
            'total_alumni_belum_final' => true,
            'pelatihan_saya' => (int) auth()->user()?->userTrainings()->count(),
            'kelengkapan_profil' => $this->persentaseKelengkapanProfil(),
        ];
    }

    /**
     * Persentase kelengkapan profil milik user yang sedang login.
     *
     * Dihitung dari field wajib profil yang terisi (bukan dari data orang lain).
     */
    public function persentaseKelengkapanProfil(): int
    {
        $user = auth()->user();

        if ($user === null) {
            return 0;
        }

        $profil = $user->userProfile;

        $field = [
            $user->name,
            $user->email,
            $profil->gender,
            $profil->birth_place,
            $profil->birth_date,
            $profil->ktp_address,
            $profil->ktp_city_id,
            $profil->education_degree_id,
            $profil->religion_id,
            $profil->profile_picture_path,
        ];

        $terisi = count(array_filter($field, fn ($nilai) => filled($nilai)));

        return (int) round(($terisi / count($field)) * 100);
    }
}
