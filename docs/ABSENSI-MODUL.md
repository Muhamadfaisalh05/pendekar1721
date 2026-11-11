# Modul Absensi (SEMARAK) — README Modul

Modul ini memindahkan aplikasi PHP native **SEMARAK** (`c:\laragon\www\Dinsos\semarak`)
ke dalam aplikasi Laravel 13 **pendekar1721** sehingga keduanya berjalan pada satu domain.

| Aspek | Nilai |
|---|---|
| Namespace | `App\Modules\Absensi` |
| Prefix URL | `/absensi` (rute: `absensi.*`) |
| Database | koneksi kedua **`semarak`** (env `SEMARAK_DB_*`), nama tabel dipertahankan |
| Auth | **satu halaman login `/login`** untuk semua role; middleware `auth` + `EnsureAbsensiAdmin` (role `admin` & `admin_absensi`) |
| Role | `admin` → `/admin`, `admin_absensi` → `/absensi`, `client` → `/klien` (kolom `users.user_type`) |
| CSRF | aktif (grup `web`), frontend mengirim `X-CSRF-TOKEN` dari meta tag |
| Rate limit | `absensi-api` 120/menit, `absensi-writes` 30/menit |
| Aset | Vite + Tailwind build lokal, **tanpa CDN** |
| Timezone | `Asia/Jakarta` |

## Struktur

```
app/Modules/Absensi/
├── Exceptions/ApiException.php
├── Http/
│   ├── Controllers/ApiController.php      # endpoint ?action= (parity native)
│   ├── Controllers/PageController.php     # 6 halaman Blade
│   └── Middleware/EnsureAbsensiAdmin.php
├── Models/                                # Anak, Kegiatan, Evaluasi,
│                                          # JadwalKegiatan, MasaPembinaan, CatatanRapor
└── Services/                              # Anak, Evaluasi, Jadwal, Ringkasan,
                                           # Rapor, Dasbor, Pembinaan
resources/views/absensi/                   # layout + 6 halaman
resources/js/absensi/                      # api.js + modul per halaman
routes/absensi.php                         # didaftarkan di bootstrap/app.php
database/migrations/semarak/               # 6 migrasi parity (guard hasTable)
tests/Feature/Absensi/                     # schema, parity, pages, filament
tests/Fixtures/Absensi/                    # oracle + seed state (lihat README di dalamnya)
```

## Login & Role

Satu halaman login: **`/login`** (`App\Http\Controllers\Auth\LoginController`).
Setelah autentikasi, pengguna diarahkan otomatis berdasarkan `users.user_type`
(`App\Models\User::dashboardUrl()`):

| Role (`user_type`) | Dashboard | Boleh `/admin` | Boleh `/klien` | Boleh `/absensi` |
|---|---|---|---|---|
| `admin` | `/admin` | ✅ | ❌ | ✅ |
| `admin_absensi` | `/absensi` | ❌ (403) | ❌ (403) | ✅ |
| `client` | `/klien` | ❌ (403) | ✅ | ❌ (403) |
| tamu | `/login` | – | – | – |

Logout: `POST /logout` untuk web/modul absensi dan `POST /admin/logout` / `POST /klien/logout`
untuk panel Filament. Semua jalur memakai `App\Http\Responses\LogoutResponse` sehingga berakhir di
`/login` beserta pesan "Anda telah keluar dari aplikasi." (tanpa error 500).

Halaman login memakai latar foto hero beranda (`public/images/hero-ppsgbr.png`) dengan overlay biru
dan kartu formulir kaca. Kolom kiri berisi brand **PENDEKAR1721** + deskripsi UPTD PPSGBR; kartu
formulir tidak memuat judul "Masuk ke akun Anda" maupun blok akun demo.

URL login panel lama sudah **dihapus**: `/admin/login` dan `/klien/login` sekarang 404.
Tamu yang membuka `/admin`, `/klien`, atau `/absensi` diarahkan ke `/login`
(panel Filament lewat `App\Http\Middleware\AuthenticatePanel`, web lewat `bootstrap/app.php`).

### Keamanan login

| Lapis | Implementasi |
|---|---|
| Rate limit berlapis | `throttle:login` (`AppServiceProvider`): 5 percobaan/menit per email+IP, 10/menit per email (tetap berlaku walau IP berubah), 20/menit per IP → HTTP 429 |
| Anti user enumeration | Pesan galat selalu sama; email tak dikenal tetap melewati `Hash::check` dengan hash dummy (waktu respons seragam) |
| Session fixation | `session()->regenerate()` setelah kredensial & role lolos; logout memakai `invalidate()` + `regenerateToken()` |
| Session hijack | `AuthenticateSession` aktif di grup `web`: sesi otomatis diputus bila hash kata sandi berubah |
| Normalisasi input | Email di-`trim` + lowercase sebelum dicari; `max:255` untuk email & kata sandi |
| Rehash otomatis | Hash lama (cost/parameter usang) diperbarui saat login berhasil |
| Anti-cache | `GET /login` mengirim `Cache-Control: no-store, no-cache, must-revalidate, private` + `Pragma: no-cache` |
| Header keamanan | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `Content-Security-Policy` (subset `frame-ancestors`, `object-src`, `base-uri`, `form-action`) — lihat `App\Http\Middleware\SecurityHeaders` |
| Proxy tepercaya | `X-Forwarded-For` hanya dibaca bila `TRUSTED_PROXIES` diisi (cegah spoofing IP untuk melewati rate limit) |
| Audit | Percobaan login gagal dicatat `Log::warning('Percobaan login gagal', ...)` tanpa kata sandi |
| Anti indexing | Halaman login memakai `<meta name="robots" content="noindex, nofollow">` |

> Rate limit **butuh cache store persisten** (`.env`: `CACHE_STORE=database` atau `redis`).
> Jika masih `array`, hitungan percobaan login tidak tersimpan antar-request.
> Setelah mengubah `.env` jalankan `php artisan config:clear` (atau `php artisan optimize`).

Akun demo dibuat oleh `database/seeders/RoleAccountSeeder.php`
(**ganti password sebelum produksi**):

```powershell
php artisan db:seed --class=RoleAccountSeeder
```

| Role | Email | Password |
|---|---|---|
| Administrator Pendekar | `admin@pendekar1721.test` | `Admin#1721` |
| Admin Absensi (SEMARAK) | `absensi@pendekar1721.test` | `Absensi#1721` |
| Klien | `klien@pendekar1721.test` | `Klien#1721` |

Seeder memakai `updateOrCreate` berdasarkan email, jadi aman dijalankan berulang.

## Halaman

| URL | Rute | Halaman native |
|---|---|---|
| `/login` | `login` | – (halaman baru, satu pintu untuk semua role) |
| `/absensi` | `absensi.dashboard` | `index.php` |
| `/absensi/input` | `absensi.input` | `absensi.php` |
| `/absensi/anak` | `absensi.anak` | `anak.php` |
| `/absensi/summary` | `absensi.summary` | `summary.php` |
| `/absensi/rapor` | `absensi.rapor` | `report.php` |
| `/absensi/pengaturan` | `absensi.pengaturan` | `pengaturan.php` |
| `/absensi/api?action=...` | `absensi.api` / `absensi.api.write` | `api.php` |

CRUD anak & kegiatan juga tersedia di panel admin Filament: `/admin/anak` dan `/admin/kegiatan`.

### Tampilan

Tema modul absensi disamakan dengan aplikasi SEMARAK asli (`resources/sass/_absensi.scss`):
gradien ungu–biru `linear-gradient(125deg, #5b4ce2, #3977ee)` pada header & hero, latar halaman
`#f5f6ff`, kartu putih membulat (`semarak-card`, `portal-card`, `dashboard-stat`) dengan ikon
berwarna lembut. Aset di-build lewat `npm run build`; setelah mengubah SCSS/Blade jalankan
`npm run build` lalu `php artisan view:clear`.

Header modul absensi hanya memuat brand SEMARAK, tombol **Input Evaluasi**, tautan
**PENDEKAR1721**, dan tombol **Keluar** — baris menu (`Dasbor`, `Input Evaluasi`, `Data Anak`,
`Summary`, `Rapor`, `Pengaturan`) serta label nama/role pengguna di kanan header sudah
dihapus. Navigasi antar halaman memakai kartu **Menu utama** di dasbor; logo SEMARAK selalu
kembali ke `/absensi`.

## Endpoint API (parity native)

GET: `dashboard`, `anak`, `anak_list`, `anak_detail`, `kegiatan`, `evaluasi`, `settings`, `summary`, `report`
POST: `save_evaluasi`, `save_evaluasi_batch`, `settings_save`, `report_notes_save`, `anak_store`, `anak_update`, `anak_delete`

Perilaku kontrak yang **sengaja dipertahankan** (dikunci oleh 46 fixture oracle):

- List `anak`/`kegiatan`/`evaluasi` mengembalikan **array mentah**; aksi tulis memakai `{success, message, ...}`.
- Status hanya **200/400/404/500**; tidak ada 405/422.
- `POST` ke action GET tetap dilayani (`POST ?action=dashboard`).
- Validasi global `mulai`/`sampai` (format + `mulai <= sampai`) berlaku untuk semua action.
- `report` menghitung ringkasan dengan rentang asli sebelum default tanggal diterapkan.
- `anak` tidak ditemukan pada `report` menghasilkan `anak: false` (perilaku `PDO::fetch()` native).

## Menjalankan (lokal)

```powershell
# 1. konfigurasi DB modul di .env
SEMARAK_DB_CONNECTION=mysql
SEMARAK_DB_HOST=127.0.0.1
SEMARAK_DB_PORT=3306
SEMARAK_DB_DATABASE=semarak_db
SEMARAK_DB_USERNAME=root
SEMARAK_DB_PASSWORD=

# 2. cek rencana migrasi lalu jalankan
php artisan migrate --pretend --database=semarak --path=database/migrations/semarak
php artisan migrate --database=semarak --path=database/migrations/semarak

# 3. build aset
npm ci && npm run build

# 4. jalankan
php artisan serve
```

Migrasi memakai guard `Schema::hasTable()` sehingga **aman dijalankan pada database
yang sudah berisi data**: tabel yang sudah ada tidak dibuat ulang dan migrasi hanya
dicatat sebagai `Ran`.

## Pengujian

```powershell
php artisan test
```

Test modul:

- `AbsensiSchemaTest` — 6 tabel parity, enum, unique, cascade, timezone.
- `AbsensiApiParityTest` — 46 skenario oracle dibandingkan **byte-per-byte** + aturan akses.
- `AbsensiPagesTest` — 6 halaman tampil, kop rapor, navigasi, akses tamu/klien.
- `AbsensiFilamentTest` — resource anak & kegiatan hanya untuk admin.

Membuat ulang oracle (tidak menyentuh folder sumber):

```powershell
php -S 127.0.0.1:8123 -t c:\laragon\www\Dinsos\_oracle\semarak
php tests/Support/Absensi/capture-oracle.php http://127.0.0.1:8123
```

## Dokumen terkait

- Panduan deploy: [`docs/ABSENSI-DEPLOY.md`](ABSENSI-DEPLOY.md)
- Daftar perbedaan perilaku: [`docs/ABSENSI-PERBEDAAN.md`](ABSENSI-PERBEDAAN.md)
- Metode oracle: [`tests/Fixtures/Absensi/README.md`](../tests/Fixtures/Absensi/README.md)
