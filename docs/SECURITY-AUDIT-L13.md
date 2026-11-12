# Audit & Hardening Keamanan — PENDEKAR1721

**Status dokumen:** diperbarui **12 November 2026** (Fase 4 — perapian penuh).
Versi ini menggantikan catatan audit 10 November 2026; riwayat lengkap tetap tersedia di git:

```bash
git log -p -- docs/SECURITY-AUDIT-L13.md
git show HEAD:docs/SECURITY-AUDIT-L13.md   # versi 10 Nov 2026 sebelum perapian (1.139 baris)
```

**Lingkup:** panel `/admin`, panel `/klien`, modul `/absensi`, Import Data (XLSX/CSV/DOCX),
profil publik `/profile/{slug}`.
**Prinsip:** *no business-logic change* — setiap patch menambah kontrol keamanan, bukan mengubah
aturan bisnis. Perubahan aturan bisnis dicatat terpisah di `docs/BACKUP-LOGIC-SEBELUM-REFACTOR.md`.

## 0. Peta dokumen & legenda status

| Dokumen | Isi |
|---|---|
| `docs/SECURITY-AUDIT-L13.md` (ini) | temuan audit, status penutupan, kontrol yang aktif, risiko residual |
| `docs/BACKUP-LOGIC-SEBELUM-REFACTOR.md` | rencana + catatan eksekusi refactor panel Klien (Fase 0–3 selesai, Fase 4 = dokumen ini) |
| `docs/AUDIT-PRODUKSI-USERS.md` | petunjuk audit produksi (read-only) + status backfill `users.graduated_at` |
| `docs/ABSENSI-DEPLOY.md`, `docs/ABSENSI-MODUL.md`, `docs/ABSENSI-PERBEDAAN.md` | modul absensi SEMARAK (di luar lingkup audit ini) |
| `docs/backup-pra-refactor/` | salinan kode panel Klien versi lama (cetak biru, tidak pernah dijalankan) |

Legenda status:

- **SELESAI** — sudah diperbaiki di kode dan dikunci oleh tes otomatis.
- **TIDAK BERLAKU** — temuan hilang karena kode terkait dihapus/ditulis ulang.
- **SEBAGIAN** — bagian utama ditutup, sisa risiko dicatat di §4.
- **TERBUKA** — belum dikerjakan, tercatat di backlog §4.

> **Catatan penomoran fase.** "FASE 0–4" di dokumen ini adalah **fase remediasi keamanan**
> (10 November 2026: triage kritis, rotasi `APP_KEY`, kepemilikan data, hardening import, config/session).
> "Fase 0–4" di `BACKUP-LOGIC-SEBELUM-REFACTOR.md` adalah **fase refactor panel Klien**
> (hardening → bersihkan panel → onboarding kelulusan → impor binaan → perapian dokumen).
> Keduanya berbeda dan tidak saling menggantikan.

## 1. Ringkasan eksekutif (per 12 November 2026)

### 1.1 Temuan audit 10 November 2026

| # | Severity | Temuan | Status | Penutup / bukti |
|---|---|---|---|---|
| 1 | CRITICAL | Backdoor `update_users.php` (dan `check_user.php`, `scratch_schema.php`, `test_import.php`) menjalankan bootstrap Laravel + query DB tanpa autentikasi | **SELESAI** | Dipindah ke `_backups/scripts/`, `git rm --cached`, `/_backups/` masuk `.gitignore` |
| 2 | CRITICAL | `.env.bak` (berisi `APP_KEY` identik dengan `.env`) dan `composer.lock.bak` ada di disk proyek | **SELESAI** | Kedua berkas dihapus; `.env.*`/`*.bak` di-ignore; document root wajib `public/` (§3.3) |
| 3 | CRITICAL | Kredensial admin `admin@admin.com` / `admin123` valid | **SELESAI** | Password dirotasi `Str::password(20)`; `Hash::check('admin123', …)` = false; `APP_KEY` dirotasi (FASE 1) |
| 4 | HIGH | IDOR profil: `/profile/{slug}-{id}` membuka user `Draft`/`Archived` (HTTP 200) | **SELESAI** | `App\Http\Controllers\Front\UserProfileController` memfilter `user_type = client` **dan** `status = Published`; `PublicProfileVisibilityTest` |
| 5 | HIGH | `Model::unguard()` global mematikan proteksi mass-assignment | **SELESAI** | `unguard()` dihapus; `$fillable` eksplisit di semua model; `user_id` relasi tidak fillable; `MassAssignmentTest` |
| 6 | HIGH | Panel `/klien` tidak di-scope per pemilik → klien dapat melihat/mengubah peserta lain | **TIDAK BERLAKU** | `PesertaResource` + halaman import klien **dihapus** (Fase 1 refactor). Panel klien kini read-only + `UserProfileEditPage` milik sendiri; `ClientPanelMenuTest`, `ProfileOwnershipTest` |
| 7 | HIGH | Import membuat akun dengan password statis `password123` | **SELESAI** | Password acak `config('pendekar.import.password_length')` atau kata sandi awal yang diverifikasi terhadap `IMPORT_PASSWORD_HASH`; mode binaan `password = NULL`; `ImportHardeningTest` |
| 8 | HIGH | Import otomatis `status = published` (tanpa moderasi) | **SEBAGIAN** | Mode **binaan** (default UI admin) membuat akun `Draft`; mode **klien** tetap `Published` karena dipakai mendaftarkan alumni aktif (keputusan produk, `BACKUP-LOGIC…` §15.2 butir 1) |
| 9 | MEDIUM | Tidak ada security header (XFO, nosniff, Referrer-Policy, HSTS) | **SELESAI** | `App\Http\Middleware\SecurityHeaders` (global) + CSP subset `frame-ancestors/object-src/base-uri/font-src/form-action`; `SecurityHeadersTest`, `SelfHostedAssetsTest` |
| 10 | MEDIUM | `SESSION_LIFETIME=3600` (60 jam) & cookie session tanpa flag `Secure` | **SELESAI (kode)** | `config/session.php`: default 120 menit, `Secure` otomatis bila `APP_URL` https, `http_only`, `same_site=lax`. Nilai `.env` produksi dicek manual (§3.1) |
| 11 | MEDIUM | `trustProxies(at: '*')` → `X-Forwarded-For` dapat dipalsukan | **SELESAI** | Default **tidak mempercayai proxy**; hanya aktif bila `TRUSTED_PROXIES` diisi (`config/app.php` + `AppServiceProvider::register()`) |
| 12 | LOW | `.DS_Store` tersebar di `public/`, `app/`, `database/`, root | **TERBUKA** | Belum dibersihkan/di-ignore — sisa risiko §4 |
| 13 | LOW | Typo `$gender22` di `HomeController` | **TIDAK BERLAKU** | Controller kini `app/Http/Controllers/Front/HomeController.php`, filter memakai variabel `$gender` yang benar |
| 14 | LOW | Upload memakai `preserveFilenames()` | **SEBAGIAN** | File data import: disk `local` (privat) + nama diacak. Foto profil **sengaja** tetap `preserveFilenames()` karena dicocokkan dengan kolom "Nama File Foto" — sisa risiko §4 |

### 1.2 Temuan tambahan yang muncul saat refactor (Fase 0–3)

| # | Severity | Temuan | Status | Penutup / bukti |
|---|---|---|---|---|
| A | HIGH | Akun `Draft`/`Archived` tetap bisa membuka panel (hanya `user_type` yang diperiksa) | **SELESAI** | `User::canAccessPanel()` menolak `Draft`/`Archived`; `LoginController` menolak dengan pesan aktivasi; `AccountStatusLoginTest` |
| B | HIGH | `users.password` NOT NULL → akun binaan (belum boleh login) mustahil dibuat | **SELESAI** | Migrasi `2026_11_12_090100_make_users_email_and_password_nullable.php` + `LoginController::kredensialValid()` menolak password kosong dengan pesan yang sama seperti kredensial salah |
| C | MEDIUM | Aksi hapus user dapat menghapus diri sendiri atau administrator terakhir | **SELESAI** | `UserAccountService::alasanTidakBolehDihapus()`, tombol hapus disembunyikan, `DeleteBulkAction` dihapus; `UserAccountGuardTest` |
| D | MEDIUM | Definisi "alumni" belum punya penanda resmi → angka dashboard memakai definisi sementara | **SELESAI (kode) / TERBUKA (data)** | `User::scopeAlumni()` + `users.graduated_at`; `Dashboard::statistikAlumni()` memakai `alumni()->count()` begitu ada minimal satu alumni, dan **hanya selama belum ada** memakai fallback definisi lama (`client` + `published`) dengan label "Angka sementara (belum final)"; `AlumniStatistikDashboardTest`. **Backfill data produksi belum dijalankan** (§4) |

## 2. Kontrol keamanan yang aktif sekarang

Peta kontrol → implementasi → tes penjaga. Semua tes dijalankan dengan
`php artisan test` (SQLite in-memory, tidak menyentuh DB dev/produksi).

| Kontrol | Implementasi | Tes penjaga |
|---|---|---|
| Satu pintu login untuk semua role | `App\Http\Controllers\Auth\LoginController` + redirect `dashboardUrl()` | `UnifiedLoginTest`, `LoginSecurityTest` |
| Rate limit login 3 lapis | `AppServiceProvider::boot()`: 5/menit per email+IP, 10/menit per email, 20/menit per IP → HTTP 429 + `Retry-After` | `LoginSecurityTest` |
| Penolakan akun non-aktif & password kosong | `User::canAccessPanel()`, `LoginController::kredensialValid()` (timing-safe dummy hash) | `AccountStatusLoginTest` |
| Rehash otomatis password lama | `Hash::needsRehash()` setelah login sukses | `LoginSecurityTest` |
| Sesi diputus saat password berubah | `$middleware->authenticateSessions()` di `bootstrap/app.php` | — (perilaku framework) |
| Header keamanan + CSP subset | `App\Http\Middleware\SecurityHeaders` (global) | `SecurityHeadersTest`, `SelfHostedAssetsTest` |
| Trusted proxy opt-in | `config('app.trusted_proxies')` (`TRUSTED_PROXIES`) → `TrustProxies::at()`; default kosong | — (konfigurasi) |
| Mass assignment tertutup | `$fillable` eksplisit, `Model::unguard()` tidak dipakai | `MassAssignmentTest` |
| Pemisahan panel & menu | `User::canAccessPanel()`, `canAccessAbsensi()`, `canAccess()` per resource | `PanelAuthorizationTest`, `ClientPanelMenuTest` |
| Privasi dashboard klien | agregat + data milik sendiri, tanpa daftar peserta | `ClientDashboardPrivacyTest`, `AlumniStatistikDashboardTest` |
| Profil publik hanya `Published` | `Front\UserProfileController` + `HomeController` filter status | `PublicProfileVisibilityTest` |
| Kepemilikan profil sendiri | `UserProfileEditPage` memakai `auth()->id()`, `user_id` tidak fillable | `ProfileOwnershipTest` |
| Guard akun admin | `UserAccountService` (tidak hapus diri sendiri / admin terakhir) | `UserAccountGuardTest` |
| Import: batas baris & password | `config/pendekar.php` (`max_rows`, `password_hash`, `password_length`), password acak / kata sandi awal terverifikasi, mode binaan `password = NULL`, audit `Log::info('import.peserta')` | `ImportHardeningTest`, `ImportBinaanTest` |
| Import: file mentah tidak publik | disk `local`, `visibility('private')`, nama diacak | `ImportHardeningTest` |
| Onboarding kelulusan | `GraduationService` (email unik dengan ignore akun tertaut, kompensasi status, kata sandi tampil sekali) | `GraduationOnboardingTest`, `UserAccountGuardTest` |
| Definisi alumni tunggal | `User::scopeAlumni()`: `client` + `published` + `graduated_at IS NOT NULL` | `GraduationOnboardingTest`, `AlumniStatistikDashboardTest` |
| Rate limit modul absensi | 120 baca/menit & 30 tulis/menit per user/IP | `AbsensiApiParityTest` (parity), limit di `AppServiceProvider` |

## 3. Konfigurasi produksi yang wajib

### 3.1 Variabel `.env` kritis

| Key | Nilai dev (12 Nov 2026) | Wajib di produksi | Alasan |
|---|---|---|---|
| `APP_ENV` | `local` | `production` | mematikan tooling debug |
| `APP_DEBUG` | `false` | **`false`** | stack trace membocorkan path & kredensial |
| `APP_URL` | `http://localhost:8000` | `https://…` | dasar `Secure` cookie otomatis & URL absolut |
| `SESSION_LIFETIME` | `120` | `120` (maks. 480) | nilai lama `3600` (60 jam idle) terlalu panjang untuk data PII |
| `SESSION_SECURE_COOKIE` | tidak diset | `true` (eksplisit) | jangan bergantung pada `APP_URL` |
| `SESSION_HTTP_ONLY` / `SESSION_SAME_SITE` | default (`true` / `lax`) | eksplisit `true` / `lax` | cegah pencurian cookie & CSRF lintas situs |
| `SESSION_ENCRYPT` | `false` | `true` (opsional) | payload sesi tidak terbaca bila DB bocor |
| `SESSION_DRIVER` | `database` | `database` / `redis` | jangan `file` di cluster |
| `LOG_LEVEL` | `debug` | `warning` | cegah PII di log |
| `BCRYPT_ROUNDS` | default `12` | `12` | sudah sesuai |
| `TRUSTED_PROXIES` | tidak diset | IP/CIDR load balancer (bila ada) | default aman: tidak ada proxy dipercaya |
| `IMPORT_MAX_ROWS` | tidak diset (default `2000`) | `2000` (atau lebih kecil) | cegah DoS/memory bloat |
| `IMPORT_PASSWORD_LENGTH` | tidak diset (default `32`) | `32` | panjang kata sandi acak peserta |
| `IMPORT_PASSWORD_HASH` | tidak diset | hash bcrypt bila memakai "Kata Sandi Awal" | fail-closed bila kosong (field selalu ditolak) |
| `PENDEKAR_ALUMNI_DIRECTORY` | tidak diset (`false`) | `false` kecuali disetujui | direktori alumni read-only opsional |

```dotenv
# potongan .env produksi
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pendekar.dinsos.go.id
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true
LOG_LEVEL=warning
TRUSTED_PROXIES=
IMPORT_MAX_ROWS=2000
```

> **Larangan yang tetap berlaku:** jangan `vendor:publish --tag=laravel-config`; jangan menyalin
> `config/session.php`/`config/cache.php` dari skeleton L13; jangan set `SESSION_SERIALIZATION=json`
> (memicu logout massal). Setelah mengubah `.env`: `php artisan config:clear` (atau `config:cache`).

### 3.2 Trusted proxies

Proxy hanya dipercaya bila daftarnya eksplisit (default: tidak ada). Isi dengan IP/CIDR nyata
reverse proxy, bukan `*`:

```dotenv
# contoh: nginx di host yang sama + jaringan Docker
TRUSTED_PROXIES=127.0.0.1,172.16.0.0/12
```

### 3.3 Verifikasi pasca-deploy

```powershell
# 1) Cookie: harus memuat secure; httponly; samesite=lax
curl.exe -s -D - -o NUL https://pendekar.dinsos.go.id/login | Select-String 'set-cookie'

# 2) Header keamanan
curl.exe -s -D - -o NUL https://pendekar.dinsos.go.id/ |
  Select-String 'x-frame|x-content-type|referrer-policy|strict-transport|permissions-policy|content-security-policy'

# 3) Debug mati: URL error harus halaman generik, bukan stack trace
curl.exe -s https://pendekar.dinsos.go.id/profile/tidak-ada-id

# 4) Tidak ada berkas sensitif yang dapat diakses (semua harus 404)
foreach ($p in '.env', '.env.bak', '.git/config', 'database/database.sqlite', 'composer.lock',
               'update_users.php', 'storage/logs/laravel.log') {
  "`n$p => " + (curl.exe -s -o NUL -w '%{http_code}' "https://pendekar.dinsos.go.id/$p")
}
```

Checklist manual (isi setelah deploy):

```text
[ ] document root menunjuk ke public/ (§3.3 butir 4 tidak ada yang 200)
[ ] /profile/{slug} user Published ............. 200
[ ] /profile/{slug} user Draft ................. 404
[ ] /admin (tamu) .............................. redirect /login
[ ] /klien (tamu) .............................. redirect /login
[ ] /absensi (tamu) ............................ redirect /login
[ ] login admin -> /admin/users ................ 200, CRUD normal
[ ] login klien -> /klien ...................... 200, hanya angka agregat + data sendiri
[ ] /klien/alumni .............................. 404 (default) atau read-only tanpa email
[ ] import 1 baris mode binaan ................. akun Draft tanpa email/password, tidak bisa login
[ ] Set-Cookie ................................. secure; httponly; samesite=lax
[ ] storage/logs/laravel.log ................... tidak ada DecryptException / MassAssignmentException
```

### 3.4 Rollback

| Perubahan | Cara rollback |
|---|---|
| Hardening header/config | kembalikan `.env`/`config` dari commit sebelumnya, `php artisan config:clear` |
| Rotasi `APP_KEY` | kembalikan `APP_KEY` dari `_backups/env/.env.before-key-rotation`, kosongkan `APP_PREVIOUS_KEYS`, `config:clear` (semua pengguna login ulang) |
| `created_by` (Fase 2 lama) | `php artisan migrate:rollback --step=1` lalu revert commit |
| Refactor panel klien / onboarding | `git revert` commit terkait; migrasi `anak_id`/`graduated_at`/email-nullable di-rollback sesuai urutan |
| Hardening import | revert commit; `IMPORT_MAX_ROWS=0` menonaktifkan batas tanpa ubah kode |

## 4. Risiko residual & backlog (per 12 November 2026)

| Prioritas | Item | Status & langkah berikutnya |
|---|---|---|
| Tinggi (data) | **Backfill `users.graduated_at`** untuk klien lama | Migrasi data berbasis **daftar `id` yang disetujui** (bukan `UPDATE` massal) belum dibuat — menunggu hasil audit produksi (`docs/AUDIT-PRODUKSI-USERS.md`). Sampai backfill dijalankan, kartu "Jumlah Alumni" hanya menghitung akun yang sudah punya `graduated_at` (bisa 0/lebih kecil dari jumlah klien sebenarnya). |
| Tinggi | 2FA untuk `/admin` | Belum ada (Filament v3 tanpa 2FA bawaan, tidak ada paket TOTP terpasang). Opsi: plugin pihak ketiga atau gerbang TOTP di `canAccessPanel()`. |
| Tinggi | `.env` lama masih ada di riwayat Git | Kredensial sudah dirotasi; purge riwayat opsional: `git filter-repo --path .env --invert-paths` (menulis ulang hash, butuh koordinasi tim). |
| Sedang | Reset password mandiri & verifikasi email | Belum aktif di panel mana pun. Reset saat ini dilakukan admin lewat `UserResource` (kata sandi tampil sekali). Pastikan `MAIL_*` produksi siap sebelum import massal mode klien. |
| Sedang | Activity log terpusat | Belum ada `spatie/laravel-activitylog`. Jejak saat ini: `Log::info('import.peserta')`, `Log::warning('import.binaan.taut_gagal')`, `Log::error('graduation.rollback')`. |
| Sedang | Foto profil import memakai `preserveFilenames()` | Disengaja: nama file dicocokkan dengan kolom "Nama File Foto" di Excel. Mitigasi lanjutan: simpan foto di disk privat + sajikan lewat route ber-otorisasi. |
| Sedang | Belum ada UI memindahkan `created_by` | Sementara lewat tinker; lihat `BACKUP-LOGIC…` §11.3. |
| Sedang | Email duplikat (case-insensitive) belum dicegah indeks DB | `GraduationService::pastikanEmailBebas()` memeriksa `LOWER(email)`, tetapi tidak ada unique index case-insensitive (SQLite/MySQL berbeda). Audit produksi wajib memastikan tidak ada duplikat sebelum backfill. |
| Sedang | CSP penuh (script/style) | Sekarang hanya subset aman (`frame-ancestors`, `object-src`, `base-uri`, `font-src`, `form-action`). CSP penuh butuh nonce untuk Filament/Livewire/Alpine dan uji staging. |
| Rendah | `.DS_Store` di root, `app/`, `database/`, `public/` | Hapus dan tambahkan ke `.gitignore` (belum dikerjakan). |
| Rendah | Document root wajib `public/` | Bukan cacat kode; salah konfigurasi Apache/Nginx mengekspos seluruh proyek. Verifikasi §3.3. |
| Rendah | `/up` healthcheck publik | Boleh dibiarkan (tanpa data sensitif) atau batasi di web server. |

## 5. Catatan perapian dokumen (Fase 4, 12 November 2026)

### 5.1 Yang dihapus dari versi 10 November 2026 dan alasannya

| Bagian lama | Alasan dihapus |
|---|---|
| Contoh patch `PesertaResource`, `CreatePeserta`, `ClientPesertaScopingTest`, `ImportPeserta` | Kode & tes tersebut **sudah dihapus** pada refactor Fase 1; panel Klien kini read-only + self-service profil, sehingga temuan "tidak di-scope" tidak berlaku |
| Usulan `must_change_password` + middleware `ForcePasswordChange` | Tidak dipakai. Mekanisme aktual: password acak 32 karakter / kata sandi awal terverifikasi hash, lalu admin mengirim kata sandi lewat aksi "Reset Kata Sandi" (tampil sekali) |
| Usulan "kata sandi impor bersama" sebagai gerbang operator | Diganti `IMPORT_PASSWORD_HASH` yang hanya mengatur kata sandi awal peserta; tidak ada gerbang kedua (sesuai alur bisnis yang disetujui) |
| Checklist rute `/klien/pesertas` | Route tidak ada lagi; diganti checklist §3.3 |
| Tabel bukti eksploitasi mentah (`/profile/bocor-2` → 200, `.env.bak` → 200, `admin123` valid) | Risiko sudah ditutup; ringkasan statusnya tetap ada di §1. Bukti lengkap dapat dilihat di riwayat Git |
| "Rencana" FASE 0–4 yang bercampur dengan catatan eksekusi | Diganti ringkasan status per temuan (§1) + peta kontrol (§2) + risiko residual (§4) |

### 5.2 Insiden operasional yang tetap dicatat

**`.env` hilang → HTTP 500 `MissingAppKeyException` (10 November 2026).** Setelah rilis
`v13.1.0-secured`, berkas `.env` tidak ditemukan di root proyek sehingga `APP_KEY` kosong dan aplikasi
menolak melayani request (fail-closed). Pemulihan: `.env` direkonstruksi dari
`_backups/env/.env.before-key-rotation`, `APP_KEY` **baru** dibuat (kunci lama dianggap bocor),
kunci lama dipasang sebagai `APP_PREVIOUS_KEYS`, lalu `php artisan config:clear`.
Tidak ada data bisnis yang hilang (tidak ada kolom ber-cast `encrypted`), hanya sesi login lama
yang tidak valid. Pencegahan: simpan salinan `.env` di password/secret manager, inject `APP_KEY`
lewat environment variabel pada hosting/CI, dan verifikasi `keyLen=44/51` + login uji setelah pemulihan.

### 5.3 Verifikasi perapian

```powershell
php artisan test            # 130 passed (557 assertions)
vendor/bin/pint --test      # PASS untuk berkas yang berubah
php artisan view:cache      # pastikan blade dashboard valid
```

## 6. Lampiran — perintah audit yang dipakai

```powershell
# Struktur & model
Get-ChildItem app\Models -File | Select-Object Name
Select-String -Path app\**\*.php -Pattern 'Model::unguard|request->all\(\)|->fill\('

# Skema & kepemilikan data
php artisan tinker --execute="echo implode(',', Illuminate\Support\Facades\Schema::getColumnListing('users'));"

# Kredensial lemah (tanpa mencetak hash)
php artisan tinker --execute="echo Illuminate\Support\Facades\Hash::check('admin123', App\Models\User::where('email','admin@admin.com')->value('password')) ? 'LEMAH' : 'ok';"

# Kebocoran profil draft
curl.exe -s -o NUL -w "%{http_code}`n" http://127.0.0.1:8000/profile/apa-saja-<id>

# Header & cookie
curl.exe -s -D - -o NUL http://127.0.0.1:8000/login

# Dependensi rentan
composer audit --locked --no-interaction

# Route tanpa auth (harus hanya halaman publik & login)
php artisan route:list --except-vendor

# Audit data produksi sebelum backfill graduated_at
php artisan tinker --execute="require base_path('docs/audit/audit-users.php');"
```
