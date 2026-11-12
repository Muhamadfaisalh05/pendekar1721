<?php

namespace App\Filament\Client\Pages;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\SchemaGuard;
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
     * DEFINISI FINAL: `total_alumni` memakai `User::scopeAlumni()` —
     * `user_type = 'client'` AND `status = 'published'` AND `graduated_at` terisi.
     * `graduated_at` klien lama diisi lewat migrasi backfill berbasis daftar `id`
     * yang disetujui hasil audit produksi (bukan `UPDATE` massal).
     *
     * FALLBACK SEMENTARA: selama **belum satu pun** klien `published` punya
     * `graduated_at` (backfill belum berjalan), kartu memakai definisi lama
     * `client` + `published` dan menandai `total_alumni_belum_final = true`
     * sehingga UI menampilkan label "Angka sementara (belum final)".
     * Begitu satu `graduated_at` terisi, hitungan otomatis beralih ke definisi
     * final (`alumni()->count()`) dan label tersebut hilang tanpa perlu deploy.
     */
    public function getStatistik(): array
    {
        $alumni = $this->statistikAlumni();

        return [
            'total_alumni' => $alumni['total'],
            'total_alumni_belum_final' => $alumni['belum_final'],
            'pelatihan_saya' => (int) auth()->user()?->userTrainings()->count(),
            'kelengkapan_profil' => $this->persentaseKelengkapanProfil(),
        ];
    }

    /**
     * Angka kartu "Jumlah Alumni" beserta status finalitasnya.
     *
     * Sumber kebenaran angka tetap `User::scopeAlumni()`. Definisi lama
     * (`client` + `published`, tanpa `graduated_at`) hanya dipakai sebagai
     * jembatan selama backfill belum dijalankan — bukan definisi bisnis baru.
     *
     * PENGAMAN SKEMA: bila kolom `graduated_at` belum ada (migrasi Fase 2
     * tertunda), dashboard TETAP tampil dengan angka definisi lama dan label
     * "Angka sementara (belum final)".
     *
     * @return array{total: int, belum_final: bool}
     */
    public function statistikAlumni(): array
    {
        if (! SchemaGuard::usersPunyaTanggalLulus()) {
            return ['total' => $this->hitungKlienPublished(), 'belum_final' => true];
        }

        $alumni = User::query()->alumni()->count();

        if ($alumni > 0) {
            return ['total' => $alumni, 'belum_final' => false];
        }

        return ['total' => $this->hitungKlienPublished(), 'belum_final' => true];
    }

    /**
     * Definisi lama sebagai jembatan: `client` + `published`.
     */
    private function hitungKlienPublished(): int
    {
        return User::query()
            ->where('user_type', User::ROLE_CLIENT)
            ->where('status', UserStatus::Published->value)
            ->count();
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
