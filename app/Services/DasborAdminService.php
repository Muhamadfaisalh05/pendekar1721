<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Modules\Absensi\Models\Anak;
use App\Support\SchemaGuard;
use App\Support\StatistikAlumni;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Angka agregat untuk dashboard Admin (`/admin`).
 *
 * ATURAN PRIVASI: kelas ini hanya mengembalikan ANGKA (count/group by).
 * Tidak ada nama, email, atau data pribadi pengguna yang pernah dibaca,
 * apalagi dikirim ke view.
 *
 * ATURAN KINERJA: seluruh hitungan dibungkus satu entri cache 5 menit
 * (`CACHE_KEY`), query lintas database `semarak` hanya COUNT/GROUP BY, dan
 * tidak ada query di dalam loop (tanpa N+1).
 *
 * ATURAN KETAHANAN (SchemaGuard): dashboard harus tetap terbuka walau
 * koneksi `semarak` mati, tabel `masa_pembinaan` belum ada, atau migrasi
 * Fase 2 (`users.anak_id`, `users.graduated_at`) belum dijalankan. Bagian
 * yang tidak bisa dihitung dikembalikan sebagai `null` sehingga UI
 * menampilkan "Data belum tersedia" — bukan error 500.
 */
final class DasborAdminService
{
    /** Kunci cache tunggal untuk seluruh angka dashboard admin. */
    public const CACHE_KEY = 'dashboard:admin:ringkasan:v1';

    /** Umur cache (detik). */
    public const TTL_DETIK = 300;

    /** Singkatan bulan (Carbon locale aplikasi = en, jadi dipetakan manual). */
    private const BULAN = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    /**
     * Ringkasan lengkap dashboard admin (di-cache 5 menit).
     *
     * @return array{
     *     anak_aktif: int|null,
     *     siap_diluluskan: int|null,
     *     akun_draft: int,
     *     alumni: array{total: int, belum_final: bool},
     *     total_klien: int,
     *     admin: int,
     *     binaan_per_angkatan: array{labels: list<string>, data: list<int>}|null,
     *     komposisi_status: array{labels: list<string>, data: list<int>},
     *     pertumbuhan: array{labels: list<string>, data: list<int>},
     *     perlu_tindakan: array{
     *         draft: int,
     *         migrasi_tertunda: bool,
     *         anak_tanpa_akun: int|null
     *     }
     * }
     */
    public function ringkasan(): array
    {
        /** @var array<string, mixed> $ringkasan */
        $ringkasan = Cache::remember(
            self::CACHE_KEY,
            now()->addSeconds(self::TTL_DETIK),
            fn (): array => $this->hitungRingkasan(),
        );

        return $ringkasan;
    }

    /**
     * Buang cache ringkasan (dipakai tes dan setelah aksi yang mengubah angka).
     */
    public function lupakan(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function hitungRingkasan(): array
    {
        return [
            'anak_aktif' => $this->hitungAnakAktif(),
            'siap_diluluskan' => $this->hitungSiapDiluluskan(),
            'akun_draft' => $this->hitungAkun(User::ROLE_CLIENT, UserStatus::Draft),
            'alumni' => StatistikAlumni::hitung(),
            'total_klien' => $this->hitungAkun(User::ROLE_CLIENT),
            'admin' => $this->hitungAkun(User::ROLE_ADMIN),
            'binaan_per_angkatan' => $this->binaanPerAngkatan(),
            'komposisi_status' => $this->komposisiStatusAkun(),
            'pertumbuhan' => $this->pertumbuhanAkun12Bulan(),
            'perlu_tindakan' => [
                'draft' => $this->hitungAkun(User::ROLE_CLIENT, UserStatus::Draft),
                'migrasi_tertunda' => SchemaGuard::migrasiFase2Tertunda(),
                'anak_tanpa_akun' => $this->hitungAnakTanpaAkun(),
            ],
        ];
    }

    /**
     * Jumlah akun pada database aplikasi (selalu tersedia).
     */
    private function hitungAkun(string $tipe, ?UserStatus $status = null): int
    {
        return User::query()
            ->where('user_type', $tipe)
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->count();
    }

    /**
     * Anak binaan berstatus `Aktif` di database SEMARAK.
     * `null` = koneksi/tabel belum tersedia.
     */
    private function hitungAnakAktif(): ?int
    {
        try {
            return (int) DB::connection('semarak')
                ->table('anak')
                ->where('status', Anak::STATUS_AKTIF)
                ->count();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Anak `Aktif` yang angkatannya sudah melewati `masa_pembinaan.tanggal_selesai`
     * (aturan yang sama dengan `PembinaanService::luluskanAngkatanSelesai()`).
     *
     * `null` = data belum tersedia (koneksi/tabel `masa_pembinaan` belum ada).
     */
    private function hitungSiapDiluluskan(): ?int
    {
        try {
            $connection = DB::connection('semarak');

            $angkatan = $connection->table('masa_pembinaan')
                ->whereNotNull('tanggal_selesai')
                ->where('tanggal_selesai', '<=', now()->toDateString())
                ->pluck('angkatan')
                ->all();

            if ($angkatan === []) {
                return 0;
            }

            return (int) $connection->table('anak')
                ->where('status', Anak::STATUS_AKTIF)
                ->whereIn('angkatan', $angkatan)
                ->count();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Anak `Aktif` yang belum punya akun Klien tertaut (`users.anak_id`).
     *
     * `null` = kolom tautan belum dimigrasikan atau koneksi SEMARAK mati.
     */
    private function hitungAnakTanpaAkun(): ?int
    {
        if (! SchemaGuard::usersPunyaTautanAnak()) {
            return null;
        }

        try {
            // Satu query untuk daftar id tertaut (bukan per baris), lalu satu
            // COUNT ke SEMARAK — tetap dua query berapa pun jumlah anaknya.
            $tertaut = User::query()
                ->whereNotNull('anak_id')
                ->pluck('anak_id')
                ->all();

            return (int) DB::connection('semarak')
                ->table('anak')
                ->where('status', Anak::STATUS_AKTIF)
                ->when($tertaut !== [], fn ($query) => $query->whereNotIn('id', $tertaut))
                ->count();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Distribusi anak per angkatan (10 angkatan terbesar).
     *
     * @return array{labels: list<string>, data: list<int>}|null
     */
    private function binaanPerAngkatan(): ?array
    {
        try {
            $baris = DB::connection('semarak')
                ->table('anak')
                ->selectRaw('angkatan, COUNT(*) as jumlah')
                ->groupBy('angkatan')
                ->orderByDesc('jumlah')
                ->limit(10)
                ->pluck('jumlah', 'angkatan');

            return [
                'labels' => $baris->keys()->map(fn ($angkatan): string => (string) $angkatan)->all(),
                'data' => $baris->values()->map(fn ($jumlah): int => (int) $jumlah)->all(),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Komposisi status akun Klien (Draft / Published / Archived) — ketiga
     * status selalu dikirim (0 bila tidak ada) supaya legenda grafik tetap.
     *
     * @return array{labels: list<string>, data: list<int>}
     */
    private function komposisiStatusAkun(): array
    {
        $jumlah = User::query()
            ->where('user_type', User::ROLE_CLIENT)
            ->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $labels = [];
        $data = [];

        foreach (UserStatus::cases() as $status) {
            $labels[] = $status->getLabel();
            $data[] = (int) ($jumlah[$status->value] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Jumlah akun Klien baru per bulan selama 12 bulan terakhir.
     *
     * `GROUP BY` memakai ekspresi bulan sesuai driver (SQLite `strftime`,
     * MySQL `DATE_FORMAT`) supaya tetap satu query, bukan 12 query.
     *
     * @return array{labels: list<string>, data: list<int>}
     */
    private function pertumbuhanAkun12Bulan(): array
    {
        $awal = now()->startOfMonth()->subMonths(11)->startOfDay();

        try {
            $jumlah = User::query()
                ->where('user_type', User::ROLE_CLIENT)
                ->where('created_at', '>=', $awal)
                ->selectRaw($this->ekspresiBulan().' as bulan, COUNT(*) as jumlah')
                ->groupBy('bulan')
                ->pluck('jumlah', 'bulan');
        } catch (Throwable) {
            $jumlah = collect();
        }

        $labels = [];
        $data = [];

        for ($i = 0; $i < 12; $i++) {
            /** @var Carbon $bulan */
            $bulan = $awal->copy()->addMonths($i);
            $kunci = $bulan->format('Y-m');

            $labels[] = self::BULAN[$bulan->month].' '.$bulan->format('y');
            $data[] = (int) ($jumlah[$kunci] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Ekspresi SQL pengelompokan bulan sesuai driver database aktif.
     */
    private function ekspresiBulan(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql' => "to_char(created_at, 'YYYY-MM')",
            default => "DATE_FORMAT(created_at, '%Y-%m')",
        };
    }
}
