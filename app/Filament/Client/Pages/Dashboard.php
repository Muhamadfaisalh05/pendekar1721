<?php

namespace App\Filament\Client\Pages;

use App\Enums\UserStatus;
use App\Filament\Client\Resources\AlumniResource;
use App\Filament\Client\Resources\TrainingResource;
use App\Models\MasterTraining;
use App\Models\User;
use App\Support\StatistikAlumni;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Collection;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.client.pages.dashboard';

    protected static ?string $title = 'Dashboard Klien';

    public static function canAccess(): bool
    {
        return auth()->user()?->user_type === 'client';
    }

    /**
     * Field profil yang dihitung untuk "Kelengkapan Profil".
     *
     * Satu sumber kebenaran: dipakai untuk persentase DAN untuk daftar kolom
     * yang masih kosong, supaya angka dan daftar tidak pernah berbeda.
     *
     * @var array<string, string>
     */
    private const FIELD_PROFIL = [
        'name' => 'Nama lengkap',
        'email' => 'Email',
        'gender' => 'Jenis kelamin',
        'birth_place' => 'Tempat lahir',
        'birth_date' => 'Tanggal lahir',
        'ktp_address' => 'Alamat KTP',
        'ktp_city_id' => 'Kota KTP',
        'education_degree_id' => 'Pendidikan terakhir',
        'religion_id' => 'Agama',
        'profile_picture_path' => 'Foto profil',
    ];

    /**
     * Statistik ringkas untuk dashboard klien.
     *
     * SECURITY (F-01): dashboard klien TIDAK menampilkan daftar nama/email
     * user lain. Semua angka di sini agregat atau milik user yang login.
     */
    public function getStatistik(): array
    {
        $alumni = $this->statistikAlumni();

        return [
            'total_alumni' => $alumni['total'],
            'total_alumni_belum_final' => $alumni['belum_final'],
            'pelatihan_saya' => (int) ($this->getPelatihanSaya()->count()),
            'kelengkapan_profil' => $this->persentaseKelengkapanProfil(),
            'kolom_profil_kosong' => $this->getKolomProfilKosong(),
        ];
    }

    /**
     * Angka kartu "Jumlah Alumni" beserta status finalitasnya.
     *
     * Sumber kebenaran angka ada di `App\Support\StatistikAlumni` supaya
     * dashboard Klien dan dashboard Admin tidak pernah berbeda. Selama
     * backfill `graduated_at` belum berjalan (atau kolomnya belum ada),
     * angka memakai definisi lama dan diberi label
     * "Angka sementara (belum final)".
     *
     * @return array{total: int, belum_final: bool}
     */
    public function statistikAlumni(): array
    {
        return StatistikAlumni::hitung();
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

        $nilai = $this->nilaiProfil($user);
        $terisi = count(array_filter($nilai, fn ($isi) => filled($isi)));

        return (int) round(($terisi / count($nilai)) * 100);
    }

    /**
     * Label field profil yang masih kosong (untuk daftar "yang perlu dilengkapi").
     *
     * @return list<string>
     */
    public function getKolomProfilKosong(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        $kosong = [];

        foreach ($this->nilaiProfil($user) as $kunci => $isi) {
            if (blank($isi)) {
                $kosong[] = self::FIELD_PROFIL[$kunci];
            }
        }

        return $kosong;
    }

    /**
     * Pelatihan milik user yang login (read-only).
     *
     * @return Collection<int, MasterTraining>
     */
    public function getPelatihanSaya(): Collection
    {
        $user = auth()->user();

        if ($user === null) {
            return collect();
        }

        return $user->userTrainings()->orderBy('title')->get();
    }

    /**
     * Status akun dalam bahasa yang ramah klien.
     */
    public function getStatusAkun(): string
    {
        return match (auth()->user()?->status) {
            UserStatus::Published => 'Aktif',
            UserStatus::Draft => 'Menunggu aktivasi',
            UserStatus::Archived => 'Diarsipkan',
            default => 'Tidak diketahui',
        };
    }

    /**
     * Tautan cepat panel Klien.
     *
     * Hanya halaman milik sendiri: profil & pelatihan. Direktori alumni
     * ditambahkan HANYA bila diaktifkan lewat config (default mati).
     *
     * @return list<array{label: string, keterangan: string, ikon: string, url: string}>
     */
    public function getTautanCepat(): array
    {
        $tautan = [
            [
                'label' => 'Ubah Profil',
                'keterangan' => 'Perbarui data diri, KTP, dan foto profil',
                'ikon' => 'heroicon-o-pencil-square',
                'url' => UserProfileEditPage::getUrl(),
            ],
            [
                'label' => 'Data Pelatihan',
                'keterangan' => 'Riwayat pelatihan yang Anda ikuti (hanya baca)',
                'ikon' => 'heroicon-o-academic-cap',
                'url' => TrainingResource::getUrl('index'),
            ],
        ];

        if (config('pendekar.alumni.directory_enabled')) {
            $tautan[] = [
                'label' => 'Data Alumni',
                'keterangan' => 'Daftar alumni (tanpa kontak pribadi)',
                'ikon' => 'heroicon-o-user-group',
                'url' => AlumniResource::getUrl('index'),
            ];
        }

        return $tautan;
    }

    /**
     * Nilai mentah field profil untuk perhitungan kelengkapan.
     *
     * @return array<string, mixed>
     */
    private function nilaiProfil(User $user): array
    {
        $profil = $user->userProfile;

        return [
            'name' => $user->name,
            'email' => $user->email,
            'gender' => $profil->gender,
            'birth_place' => $profil->birth_place,
            'birth_date' => $profil->birth_date,
            'ktp_address' => $profil->ktp_address,
            'ktp_city_id' => $profil->ktp_city_id,
            'education_degree_id' => $profil->education_degree_id,
            'religion_id' => $profil->religion_id,
            'profile_picture_path' => $profil->profile_picture_path,
        ];
    }
}
