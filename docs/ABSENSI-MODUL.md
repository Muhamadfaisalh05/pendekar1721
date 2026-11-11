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

Logout: `POST /logout` (tersedia di header modul absensi dan di panel Filament).

Halaman login memakai latar foto hero beranda (`public/images/hero-ppsgbr.png`) dengan overlay biru
dan kartu formulir kaca; blok akun demo hanya tampil saat `APP_ENV=local`.

URL login panel lama sudah **dihapus**: `/admin/login` dan `/klien/login` sekarang 404.
Tamu yang membuka `/admin`, `/klien`, atau `/absensi` diarahkan ke `/login`
(panel Filament lewat `App\Http\Middleware\AuthenticatePanel`, web lewat `bootstrap/app.php`).

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
