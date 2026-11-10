# Security Audit & Hardening — PENDEKAR1721 (Laravel 13.40 / PHP 8.4)

**Peran:** Senior Cyber Security Auditor & Laravel Security Specialist
**Tanggal audit:** 10 November 2026
**Scope:** Dual panel Filament (`/admin`, `/klien`), Import Data (XLSX/CSV/DOCX), Profil Publik (`/profile/{slug}`)
**Prinsip:** *no business-logic change* — semua patch di bawah bersifat menambah kontrol, bukan mengubah aturan bisnis.

---

## 0. Ringkasan Eksekutif

Audit dilakukan secara **static review + dynamic testing** pada aplikasi yang berjalan (PHP 8.4.2, Laravel 13.40.0).
Tiga kelas risiko utama ditemukan **terbukti dapat dieksploitasi** di mesin audit:

| Severity | Temuan | Status |
|---|---|---|
| **CRITICAL** | Backdoor kredensial: `update_users.php` me-reset admin jadi `admin@admin.com / admin123`; password tersebut **valid** di database | Perlu tindakan (FASE 0) |
| **CRITICAL** | `.env.bak` (berisi `APP_KEY` identik dengan `.env`) dapat diunduh bila document root salah arah | Perlu tindakan (FASE 0) |
| **HIGH** | Profil **Draft/Archived** dapat dibuka via `/profile/apa-saja-{id}` → HTTP 200 | ✅ **DIPERBAIKI + test** |
| **HIGH** | `Model::unguard()` global → proteksi mass-assignment mati untuk seluruh model | Perlu tindakan (FASE 2) |
| **HIGH** | Panel `/klien` tidak di-scope per pemilik → klien bisa melihat & meng-edit seluruh peserta | Perlu keputusan bisnis (FASE 2) |
| **HIGH** | Import membuat user dengan password statis `password123` | Perlu tindakan (FASE 3) |
| **MEDIUM** | Tidak ada security header (XFO, nosniff, Referrer-Policy, HSTS) | ✅ **DIPERBAIKI + test** |
| **MEDIUM** | `SESSION_LIFETIME=3600` (60 jam idle) & cookie session tanpa flag `Secure` | ✅ **DIPERBAIKI (default)** + sisa env (FASE 4) |
| **MEDIUM** | `trustProxies(at: '*')` → `X-Forwarded-For` dapat dipalsukan | Perlu tindakan (FASE 4) |
| **LOW** | `.DS_Store` di `public/`, typo `$gender22`, upload `preserveFilenames()` | Backlog |

**Yang sudah baik (dipertahankan):** `composer audit` bersih (0 advisory), Filament login sudah punya rate-limit 5 percobaan,
CSRF aktif lewat `PreventRequestForgery`, Blade auto-escape (tidak ada `{!! !!}` pada input user), route download
Filament `filament.actions` sudah `['web','auth']`, `User::canAccessPanel()` memisahkan admin/klien dengan benar (dibuktikan test),
`password` memakai cast `hashed`, dan tidak ada kolom ber-cast `encrypted` (mempermudah rotasi `APP_KEY`).

---

## 1. Bukti Pengujian (evidence)

### 1.1 IDOR profil Draft/Archived — SEBELUM patch

```
/profile/bocor-2   => 200   (user status=draft)
/profile/bocor-3   => 200   (user status=archived)
/profile/tanpa-id  => 404
```

Penyebab: `HomeController` memfilter `status = Published`, tetapi `UserProfileController` hanya memfilter
`user_type = client`. Halaman detail menjadi jalur bypass filter listing (klasik *broken access control*, OWASP A01).

### 1.2 Setelah patch

```
/profile/x-999 => 404
php artisan test --testsuite=Feature  →  14 passed (34 assertions)
```

### 1.3 Kebocoran `.env.bak` saat document root salah (simulasi `php -S -t <root proyek>`)

```
/.env.bak       => 200
APP_NAME=Laravel
APP_ENV=local
APP_KEY=base64:hpv6******************************   ← kunci asli, identik dengan .env aktif
/check_user.php => 200 (mengeksekusi Laravel + query DB tanpa login)
```

Dengan document root yang benar (`public/`), semua path di atas `404` (sudah diverifikasi).
Artinya: **risiko bukan pada Laravel, tapi pada konfigurasi web server** — dan file-nya memang ada di disk.

### 1.4 Kredensial lemah aktif

```
admin@admin.com ada: YA
password "admin123" valid: YA   (KRITIS)
```

### 1.5 Cookie session sebelum hardening

```
Set-Cookie: laravel_session=...; expires=...; Max-Age=216000; path=/; httponly; samesite=lax
```

`Max-Age=216000` = 60 jam (dari `SESSION_LIFETIME=3600` menit) dan **tanpa** `Secure`.

---

## 2. FASE 0 — Triage kritis (lakukan hari ini, ±15 menit)

### 2.1 Hapus backdoor & file sensitif dari disk

```powershell
cd C:\laragon\www\Dinsos\pendekar1721

# 1) File backup berisi APP_KEY/kredensial — simpan dulu di password manager, lalu hapus dari proyek
Remove-Item -LiteralPath .env.bak, composer.lock.bak -Force

# 2) Skrip utilitas yang mengeksekusi DB tanpa autentikasi -> pindahkan keluar webroot
New-Item -ItemType Directory -Force -Path _backups\scripts | Out-Null
Move-Item -LiteralPath check_user.php, update_users.php, test_import.php, scratch_schema.php -Destination _backups\scripts\
```

```powershell
# 3) Keluarkan dari index Git (file tetap ada di _backups/scripts, tapi tidak ikut ter-deploy)
git rm --cached check_user.php update_users.php test_import.php scratch_schema.php
```

> Catatan: `*.bak` sudah ada di `.gitignore`, tetapi itu hanya mencegah commit — **tidak** mencegah akses HTTP
> dan tidak menghapus file dari disk. Selama file ada di folder proyek, salah konfigurasi document root = kebocoran.

### 2.2 Cegah kambuh — tambahan `.gitignore`

```gitignore
# secrets & artefak sensitif
.env.*
!.env.example
*.bak
*.sql
*.sqlite
/database/database.sqlite
/_backups/
audit_probe*.php
.DS_Store
```

### 2.3 Verifikasi document root (akar risiko `.env` dapat diakses via URL)

```powershell
php artisan serve --host=127.0.0.1 --port=8099

# Semua HARUS 404
curl.exe -s -o NUL -w "%{http_code}`n" http://127.0.0.1:8099/.env
curl.exe -s -o NUL -w "%{http_code}`n" http://127.0.0.1:8099/.env.bak
curl.exe -s -o NUL -w "%{http_code}`n" http://127.0.0.1:8099/composer.lock
curl.exe -s -o NUL -w "%{http_code}`n" http://127.0.0.1:8099/database/database.sqlite
curl.exe -s -o NUL -w "%{http_code}`n" http://127.0.0.1:8099/update_users.php
curl.exe -s -o NUL -w "%{http_code}`n" http://127.0.0.1:8099/.git/config
```

Di produksi pastikan `DocumentRoot` (Apache) / `root` (nginx) menunjuk ke `.../pendekar1721/public`,
lalu tambahkan penolakan eksplisit sebagai defense in depth:

```apache
# public/.htaccess
<FilesMatch "^\.(env|git|htaccess)">
    Require all denied
</FilesMatch>
```

```nginx
location ~ /\.(?!well-known) { deny all; }
```

### 2.4 Ganti kredensial admin yang bocor

```powershell
php artisan tinker --execute="App\Models\User::where('email','admin@admin.com')->update(['email' => 'admin@dinsos.go.id', 'password' => Illuminate\Support\Facades\Hash::make(Illuminate\Support\Str::password(24))]);"
```

Rotasi juga kredensial yang ada di riwayat Git: password DB, SMTP, token API/WA.
`APP_KEY` **jangan** dirotasi di fase ini — ikuti FASE 1 agar sesi & data lama tetap aman.

---

## 3. FASE 1 — Rotasi `APP_KEY` + `APP_PREVIOUS_KEYS`

### 3.1 Fakta penting dari hasil audit

1. `config/app.php` **sudah** mendukung rotasi bertahap:
   ```php
   'previous_keys' => [
       ...array_filter(explode(',', env('APP_PREVIOUS_KEYS', ''))),
   ],
   ```
2. Tidak ada satu pun model yang memakai cast `encrypted`, dan tidak ada pemanggilan `Crypt::`/`encrypt()` manual.
   Artinya dampak rotasi = **semua sesi lama logout** (termasuk `remember_token`), **bukan** kehilangan data bisnis.
3. `APP_KEY` yang bocor (`.env.bak` + riwayat Git) **identik** dengan yang aktif → rotasi wajib.

### 3.2 Prosedur (zero-downtime, rollback-ready)

```powershell
# 0) Backup dulu
Copy-Item .env ".env.backup.$(Get-Date -f yyyyMMddHHmm)"   # simpan di vault, lalu hapus dari folder proyek
mysqldump -u root -p pendekar > "D:\backup\pendekar_$(Get-Date -f yyyyMMdd).sql"

# 1) Buat key baru tanpa menimpa .env
php artisan key:generate --show
# contoh keluaran: base64:NEWKEYNEWKEYNEWKEYNEWKEYNEWKEYNEWKEY=

# 2) Edit .env (manual / notepad):
#    APP_KEY=base64:NEWKEY...
#    APP_PREVIOUS_KEYS=base64:OLDKEY...
#    (JANGAN ada spasi setelah koma; jangan hapus 'base64:' di depan)

# 3) Bersihkan cache konfigurasi
php artisan config:clear
php artisan cache:clear
# jika produksi memakai config cache:
# php artisan config:cache

# 4) Verifikasi
php artisan about --only=environment
php artisan tinker --execute="echo config('app.key') ? 'key OK' : 'key KOSONG'; echo PHP_EOL; echo count(config('app.previous_keys')).' previous key(s)';"
```

### 3.3 Verifikasi end-to-end (wajib)

```powershell
php artisan serve --port=8099
# 1) Login admin + klien -> harus sukses
# 2) Buka /admin/users, /klien/pesertas -> data tetap tampil
# 3) Buka halaman lain dengan cookie sesi LAMA (browser lain) -> harus diminta login ulang (expected)
# 4) Cek log tidak ada DecryptException
Get-Content storage\logs\laravel.log -Tail 50 | Select-String 'DecryptException|MAC is invalid'
```

### 3.4 Kapan `APP_PREVIOUS_KEYS` boleh dihapus?

- Setelah **semua** sesi lama kedaluwarsa (≥ `SESSION_LIFETIME`) **dan** tidak ada payload terenkripsi lama.
- Jika nanti Anda menambahkan kolom `encrypted` cast, lakukan re-enkripsi data **sebelum** menghapus key lama.
- Rollback: kembalikan `APP_KEY` + `APP_PREVIOUS_KEYS` dari backup `.env`, `php artisan config:clear`. Tidak ada migrasi data, jadi rollback aman.

> Jangan menyalin `config/session.php`/`config/cache.php` dari skeleton Laravel 13 dan jangan set
> `SESSION_SERIALIZATION=json` — keduanya dapat memicu logout massal (larangan FASE 3 sesi upgrade sebelumnya tetap berlaku).

---

## 4. FASE 2 — Otorisasi & Hak Akses (OWASP A01)

### 4.1 Profil Draft/Archived — ✅ SUDAH DIPERBAIKI

`app/Http/Controllers/Front/UserProfileController.php`:

```php
$user = User::query()
    ->where('id', '=', (int) $userId)
    ->where('user_type', '=', 'client')
    ->where('status', '=', UserStatus::Published)   // <-- hardening
    ->firstOrFail();
```

Test regresi: `tests/Feature/Security/PublicProfileVisibilityTest.php` (5 kasus).
Dampak ke pengguna: profil **Published** tetap tampil sama; Draft/Archived kini `404` (sesuai maksud filter di beranda).

Catatan tambahan (opsional, LOW): slug tidak dipakai sama sekali selain angka di belakangnya, sehingga
`/profile/apa-saja-{id}` valid dan ID peserta dapat dienumerasi. Bila ingin memperketat, gunakan
`/profile/{slug}` dengan kolom unik (mis. `users.slug`) + `where('slug', $slug)`.

### 4.2 Pembatasan data peserta di panel `/klien` — PERLU KEPUTUSAN BISNIS

**Kondisi saat ini:** `PesertaResource::getEloquentQuery()` hanya memfilter `user_type = 'client'`:

```php
return parent::getEloquentQuery()->where('user_type', 'client');
```

Tidak ada kolom kepemilikan pada tabel `users` (`id,name,email,email_verified_at,password,user_type,remember_token,created_at,updated_at,status`).
Konsekuensi: **setiap klien melihat & dapat meng-edit seluruh peserta** (termasuk foto, alamat KTP) —
dan `ImportPeserta` (panel klien) membuat peserta baru ke kolam bersama itu.

Pilih salah satu model (rekomendasi: **Opsi A**):

#### Opsi A — Tambah kepemilikan data (paling sesuai permintaan)

1) Migrasi:

```php
// database/migrations/2026_11_10_000001_add_created_by_to_users_table.php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->foreignId('created_by')->nullable()->after('user_type')
            ->constrained('users')->nullOnDelete();
        $table->index(['user_type', 'created_by']);
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropConstrainedForeignId('created_by');
    });
}
```

2) Backfill data lama (peserta yang sudah ada belum punya pemilik):

```powershell
php artisan tinker --execute="$owner = App\Models\User::where('email','klien.utama@dinsos.go.id')->value('id'); App\Models\User::where('user_type','client')->whereNull('created_by')->update(['created_by' => $owner]); echo App\Models\User::whereNull('created_by')->where('user_type','client')->count().' peserta belum punya owner';"
```

3) Scope query resource (`app/Filament/Client/Resources/PesertaResource.php`):

```php
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery()->where('user_type', 'client');
    $user = auth()->user();

    // Klien hanya melihat data miliknya; admin (bila resource dipakai di panel admin) melihat semua.
    if ($user?->user_type === 'admin') {
        return $query;
    }

    return $query->where('created_by', $user?->id);
}
```

4) Set pemilik saat create/import:

```php
// app/Filament/Client/Resources/PesertaResource/Pages/CreatePeserta.php
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['created_by'] = auth()->id();

    return $data;
}
```

```php
// app/Services/ImportService.php — tambahkan parameter owner
public function importData(array $validData, array $uploadedPhotos = [], ?int $createdBy = null)
{
    // ...
    $user = User::create([
        'name' => $data['name'],
        'email' => $data['email'],
        'password' => Hash::make(Str::password(32)), // lihat FASE 3
        'user_type' => 'client',
        'status' => 'draft',                        // lihat FASE 3
        'created_by' => $createdBy,                 // <-- kepemilikan
    ]);
}
```

```php
// ImportPeserta::confirmImport() & ImportData::confirmImport()
$service->importData($this->previewData['valid'], $uploadedPhotos, auth()->id());
```

5) Defense in depth (pesan error jelas, bukan sekadar 404):

```php
// EditPeserta.php
protected function authorizeAccess(): void
{
    parent::authorizeAccess();

    abort_unless(
        auth()->user()?->user_type === 'admin' || $this->getRecord()->created_by === auth()->id(),
        403
    );
}
```

**Efek samping yang harus disepakati:** klien tidak lagi melihat peserta milik klien lain. Data lama tanpa
`created_by` akan "hilang" dari panel klien sampai di-backfill → lakukan backfill **sebelum** deploy kode.

#### Opsi B — Tanpa perubahan skema (paling minimal)

- Hapus/`canAccess(): false` untuk `App\Filament\Client\Pages\ImportPeserta` (klien tidak boleh menambah peserta).
- Ubah `PesertaResource` menjadi read-only (hapus `EditAction`) dan arahkan klien ke `UserProfileEditPage`
  untuk mengelola profilnya sendiri (sudah ada dan sudah ter-scope `auth()->user()->userProfile`).
- Konsekuensi: klien kehilangan fitur impor & edit peserta lain → tidak sesuai kebutuhan "import data klien",
  tetapi risiko IDOR hilang sepenuhnya tanpa migrasi.

Test otomatis yang perlu ditambahkan setelah Opsi A (contoh):

```php
// tests/Feature/Security/ClientPanelScopingTest.php
public function test_klien_tidak_bisa_mengedit_peserta_milik_klien_lain(): void
{
    $pemilik = User::create([...'user_type' => 'client']);
    $peserta = User::create([...'user_type' => 'client', 'created_by' => $pemilik->id]);
    $klienLain = User::create([...'user_type' => 'client']);

    // Route Filament: /klien/pesertas (plural kebab dari PesertaResource)
    $this->actingAs($klienLain)
        ->get("/klien/pesertas/{$peserta->id}/edit")
        ->assertNotFound();

    $this->actingAs($pemilik)
        ->get("/klien/pesertas/{$peserta->id}/edit")
        ->assertOk();
}
```

### 4.3 Audit mass-assignment — `Model::unguard()` global (HIGH)

`app/Providers/AppServiceProvider.php:36` mematikan proteksi mass-assignment untuk **seluruh** model:

```php
Model::unguard();
```

Saat ini belum ada `$request->all()` yang langsung di-`fill()`, jadi belum bisa dieksploitasi via HTTP
(Filament hanya mengirim field yang ada di schema). Namun ini adalah *single point of failure*: satu baris kode
baru (`$user->update($request->all())`) langsung menjadi eskalasi hak akses (`user_type=admin`).

**Langkah 1 — tambahkan `$fillable` ke semua model** (daftar kolom nyata dari skema):

```php
// app/Models/User.php  (WAJIB memuat user_type & status agar UserResource tetap berfungsi)
protected $fillable = ['name', 'email', 'password', 'user_type', 'status', 'email_verified_at'];

// app/Models/UserProfile.php
protected $fillable = [
    'user_id', 'description', 'birth_place', 'birth_date', 'gender',
    'ktp_address', 'ktp_city_id', 'domisili_address', 'domisili_city_id', 'education_degree_id',
    'body_weight', 'body_height', 'religion_id', 'ethnic_group_id',
    'profile_picture_path', 'skck_status', 'surat_kesehatan_status', 'hire_status', 'status',
];

// app/Models/UserExperience.php
protected $fillable = ['user_id', 'experience_year', 'experience_job_name', 'experience_description', 'sort_order', 'is_current_job'];

// MasterCity, MasterEducationDegree, MasterEthnicGroup, MasterReligion, MasterSkill, MasterTraining
protected $fillable = ['title'];
```

**Langkah 2 — matikan `Model::unguard()`** dan (opsional tapi disarankan) nyalakan strict mode:

```php
// app/Providers/AppServiceProvider.php
// HAPUS: Model::unguard();

Model::shouldBeStrict(! app()->isProduction());
```

**Langkah 3 — perbaiki satu gotcha Filament** sebelum strict mode diaktifkan:
field konfirmasi password bukan kolom DB, sehingga harus tidak di-dehydrate.

```php
// app/Filament/Resources/UserResource.php
Forms\Components\TextInput::make('password_confirmation')
    ->dehydrated(false)   // <-- tambahkan
```

**Langkah 4 — verifikasi** (jalankan test yang sudah ada + manual):

```powershell
php artisan test
# Uji manual: buat user via /admin/users, ubah user lewat /admin/users/{id}/edit,
# edit profil di /klien (UserProfileEditPage), lalu import 1 baris.
```

Test regresi yang disarankan:

```php
// tests/Feature/Security/MassAssignmentTest.php
public function test_user_type_tidak_bisa_diisi_massal(): void
{
    $this->expectException(\Illuminate\Database\Eloquent\MassAssignmentException::class);

    User::create(['name' => 'X', 'email' => 'x@example.test', 'password' => 'x', 'user_type' => 'admin']);
}
```

> Jika strict mode terasa terlalu agresif untuk produksi, minimal: hapus `unguard()` + `$fillable` lengkap.
> Itu sudah menutup jalur eskalasi tanpa mengubah perilaku form.

---

## 5. FASE 3 — Hardening alur Import (XLSX/CSV/DOCX)

### 5.1 Temuan pada `app/Services/ImportService.php`

| # | Temuan | Dampak |
|---|---|---|
| 1 | `Hash::make('password123')` untuk **setiap** user hasil import | Semua akun peserta memakai password yang sama & terpublikasi → account takeover massal |
| 2 | `'status' => 'published'` otomatis | Data masuk situs publik tanpa moderasi (spam / data uji / PII bocor) |
| 3 | Tidak ada batas jumlah baris | XLSX 50.000 baris = memory/CPU exhaustion (DoS, `IOFactory::load` memuat seluruh workbook) |
| 4 | File upload disimpan di disk default (`FILESYSTEM_DISK=local`) tetapi dibaca dari `storage_path('app/public/...')` | Tidak konsisten; bila produksi memakai `FILESYSTEM_DISK=public`, **file XLSX berisi PII dapat diunduh** via `/storage/imports/...` |
| 5 | `->preserveFilenames()` pada foto & file | Nama sama saling menimpa (integritas data); nama file user-controlled |
| 6 | Tidak ada audit log siapa mengimpor apa | Tidak ada akuntabilitas |
| 7 | `getCalculatedValue()` mengeksekusi formula sel | Formula berbahaya/berat ikut dieksekusi saat preview |

Catatan: `acceptedFileTypes()` + `maxSize(5120)` Filament **sudah** memvalidasi tipe di server (rule `mimes`),
jadi tidak perlu menambah validasi MIME dasar — cukup pertahankan.

### 5.2 Sanitasi "shared import password"

Ada dua kebutuhan berbeda:

- **Kata sandi impor bersama** = gerbang otorisasi tambahan bagi operator (admin/klien) sebelum menjalankan impor.
- **Password akun peserta** = kredensial login peserta hasil impor (saat ini `password123`).

**Jangan** menyimpan shared password sebagai plaintext di `.env`/DB. Simpan **hash**-nya:

```powershell
php artisan tinker --execute="echo Illuminate\Support\Facades\Hash::make('GantiDenganPasswordImporYangKuat#2026');"
# salin hasil $2y$... ke .env
```

```dotenv
# .env (produksi)
IMPORT_PASSWORD_HASH=$2y$12$................................................
IMPORT_MAX_ROWS=2000
IMPORT_DEFAULT_STATUS=draft
```

```php
// config/pendekar.php (file baru)
return [
    'import' => [
        'password_hash' => env('IMPORT_PASSWORD_HASH'),
        'max_rows' => (int) env('IMPORT_MAX_ROWS', 2000),
        'default_status' => env('IMPORT_DEFAULT_STATUS', 'draft'),
    ],
];
```

Tambahkan field kata sandi + throttle pada kedua halaman impor (`ImportData`, `ImportPeserta`):

```php
use Illuminate\Support\Facades\RateLimiter;

Forms\Components\TextInput::make('import_password')
    ->label('Kata Sandi Impor')
    ->password()
    ->revealable()
    ->required()
    ->dehydrated(false)   // jangan pernah dikirim ke model/DB
    ->rule(function () {
        return function (string $attribute, $value, \Closure $fail) {
            $hash = config('pendekar.import.password_hash');

            if (! $hash || ! \Illuminate\Support\Facades\Hash::check((string) $value, $hash)) {
                $fail('Kata sandi impor salah.');
            }
        };
    }),
```

```php
// di awal processPreview() dan confirmImport()
$key = 'import:' . auth()->id();

abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Terlalu banyak percobaan impor.');
RateLimiter::hit($key, 300); // 5 percobaan / 5 menit
```

> Untuk panel `/klien`, pertimbangkan **kata sandi impor berbeda per klien** (kolom `import_password_hash` di `users`)
> agar satu klien tidak bisa memakai kredensial bersama untuk menyalahgunakan kuota impor.

### 5.3 Patch minimal `ImportService` (password, status, limit, path, audit)

```php
use Illuminate\Support\Str;

public function parseAndValidate($filePath, $extension)
{
    // ... parsing ...
    $rows = $this->parseExcel($filePath);

    $max = (int) config('pendekar.import.max_rows', 2000);

    if (count($rows) > $max) {
        throw new \RuntimeException("Jumlah baris melebihi batas ({$max}). Pecah file menjadi beberapa bagian.");
    }

    return $this->validateRows($rows);
}
```

```php
public function importData(array $validData, array $uploadedPhotos = [], ?int $createdBy = null)
{
    DB::beginTransaction();

    try {
        foreach ($validData as $data) {
            // Password acak 32 karakter (tidak dapat ditebak, tidak dibagikan).
            // Peserta wajib reset password / admin mengirim undangan.
            $plainPassword = Str::password(32);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($plainPassword),
                'user_type' => 'client',
                'status' => config('pendekar.import.default_status', 'draft'), // 'draft' = perlu review
                'created_by' => $createdBy,
            ]);

            // ... profil, relasi, pengalaman (tidak berubah) ...
        }

        DB::commit();

        Log::info('import.peserta', [
            'actor_id' => $createdBy,
            'rows' => count($validData),
            'ip' => request()->ip(),
        ]);

        return true;
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Import Error: ' . $e->getMessage());
        throw $e;
    }
}
```

Jika Anda tetap butuh peserta bisa login segera setelah impor, gunakan **shared import password**
(yang sudah diverifikasi di form) sebagai password awal + tandai wajib ganti:

```php
// butuh migrasi: $table->boolean('must_change_password')->default(false);
$user->password = Hash::make($request->string('import_password')); // dari form yang sudah lolos verifikasi
$user->must_change_password = true;
```

Lalu paksa ganti di panel klien:

```php
// app/Providers/Filament/ClientPanelProvider.php — di dalam panel()
->authenticateUsing(...)   // tidak perlu
// lebih tepat: middleware khusus
// app/Http/Middleware/ForcePasswordChange.php
if (auth()->check() && auth()->user()->must_change_password && ! request()->routeIs('filament.client.auth.*', 'filament.client.pages.*')) {
    return redirect()->route('filament.client.pages.user-profile-edit');
}
```

**Penyimpanan file (private disk + nama unik):**

```php
Forms\Components\FileUpload::make('file')
    ->disk('local')                 // storage/app/private — TIDAK dapat diakses via /storage
    ->visibility('private')
    ->directory('imports/' . auth()->id())
    ->preserveFilenames(false)      // hindari overwrite & path aneh
    ->maxSize(5120)
    ->acceptedFileTypes([...]);

Forms\Components\FileUpload::make('photos')
    ->disk('public')                // foto profil memang publik
    ->directory('profile_pictures')
    ->preserveFilenames(false)      // <-- ganti dari true
    ->image()
    // ...
```

```php
// membaca file di processPreview()
$filePath = \Illuminate\Support\Facades\Storage::disk('local')->path($data['file']);
$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
```

**Formula injection / parsing aman:**

```php
protected function parseExcel($filePath)
{
    $reader = IOFactory::createReaderForFile($filePath);
    $reader->setReadDataOnly(true);          // jangan memuat style/objek berbahaya

    $spreadsheet = $reader->load($filePath);
    // ...
    $rowData[] = $cell->getValue();          // ganti dari getCalculatedValue()
}
```

> `getValue()` mengembalikan teks formula mentah (mis. `=HYPERLINK(...)`) alih-alih mengeksekusinya.
> Karena kolom import adalah data (nama/email/alamat), ini lebih aman dan tidak mengubah hasil untuk file normal.
> Saat **mengekspor** template, prefix sel yang dimulai `=`, `+`, `-`, `@` dengan `'` untuk mencegah CSV injection di Excel pengguna.

### 5.4 Test case import

```powershell
# 1) Upload file dengan ekstensi disamarkan (.xlsx berisi PHP) -> harus ditolak (mimes)
# 2) Upload .csv 2.001 baris -> preview harus menolak "melebihi batas"
# 3) Import 1 baris -> cek DB:
php artisan tinker --execute="echo App\Models\User::latest('id')->first()->status;"
#    ekspektasi: draft (bukan published)
# 4) Cek password tidak lagi 'password123':
php artisan tinker --execute="$u = App\Models\User::latest('id')->first(); echo Illuminate\Support\Facades\Hash::check('password123', $u->password) ? 'MASIH LEMAH' : 'aman';"
# 5) Cek file import tidak dapat diakses via URL:
#    http://host/storage/imports/<nama-file> -> 404
```

---

## 6. FASE 4 — Config, Cookie & Session Hardening

### 6.1 Nilai `.env` produksi yang direkomendasikan

| Key | Nilai sekarang (dev) | Rekomendasi produksi | Alasan |
|---|---|---|---|
| `APP_ENV` | `local` | `production` | Menonaktifkan debug tooling |
| `APP_DEBUG` | `true` | **`false`** | Stack trace = kebocoran kredensial & path |
| `APP_URL` | `http://localhost:8000` | `https://pendekar.dinsos.go.id` | Dipakai untuk URL & default `Secure` cookie |
| `SESSION_LIFETIME` | **`3600`** (menit = 60 jam) | **`120`** (2 jam) — maksimum `480` | Idle session terlalu panjang untuk data PII |
| `SESSION_SECURE_COOKIE` | tidak diset | `true` | Cookie tidak boleh dikirim lewat HTTP |
| `SESSION_HTTP_ONLY` | tidak diset (default `true`) | `true` (eksplisit) | Cegah pencurian cookie via XSS |
| `SESSION_SAME_SITE` | tidak diset (default `lax`) | `lax` (jangan `none`) | Mitigasi CSRF lintas situs |
| `SESSION_DOMAIN` | `null` | `null` (kecuali ada subdomain) | Cegah cookie dishare ke subdomain lain |
| `SESSION_ENCRYPT` | `false` | `true` (opsional) | Payload sesi di DB tidak terbaca jika DB bocor |
| `SESSION_DRIVER` | `database` | `database` / `redis` | Jangan `file` di cluster |
| `LOG_LEVEL` | `debug` | `warning` | Cegah PII di log |
| `BCRYPT_ROUNDS` | `12` (default) | `12` | Sudah sesuai |

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
```

> **Larangan yang tetap berlaku:** jangan `vendor:publish --tag=laravel-config`, jangan salin `config/session.php`
> atau `config/cache.php` dari skeleton L13, dan jangan set `SESSION_SERIALIZATION=json` → mencegah logout massal.

### 6.2 Yang sudah diperbaiki di kode

1. `config/session.php` — `Secure` otomatis aktif bila `APP_URL` memakai `https://`:
   ```php
   'secure' => env('SESSION_SECURE_COOKIE', str_starts_with((string) env('APP_URL', ''), 'https://')),
   ```
   Artinya produksi HTTPS otomatis aman, sementara dev HTTP tetap bisa login.
   Eksplisitkan `SESSION_SECURE_COOKIE=true` di produksi agar tidak bergantung pada `APP_URL`.

2. `app/Http/Middleware/SecurityHeaders.php` + registrasi global di `bootstrap/app.php`:
   `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`,
   `Permissions-Policy`, `X-Permitted-Cross-Domain-Policies`, dan `Strict-Transport-Security` (hanya via HTTPS).

   > CSP sengaja **tidak** ditambahkan: Filament/Livewire/Alpine memakai inline script, CSP yang salah akan
   > mematikan panel. Jika ingin CSP, gunakan `nonce` via `@vite`/Blade directive dan uji di staging.

### 6.3 Trusted proxies (`trustProxies(at: '*')`)

`bootstrap/app.php` saat ini menerima `X-Forwarded-For` dari siapa pun. Dampak: `request()->ip()` dapat dipalsukan
(bypass rate limit, log audit menyesatkan). Ganti dengan IP reverse proxy/Docker network yang nyata:

```php
use Illuminate\Http\Request;

$middleware->trustProxies(
    at: ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
    headers: Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO,
);
```

### 6.4 Verifikasi cookie & header (wajib setelah deploy)

```powershell
# 1) Flag cookie: harus ada "secure; httponly; samesite=lax"
curl.exe -s -D - -o NUL https://pendekar.dinsos.go.id/admin/login | Select-String 'set-cookie'

# 2) Security headers
curl.exe -s -D - -o NUL https://pendekar.dinsos.go.id/ | Select-String 'x-frame|x-content-type|referrer-policy|strict-transport|permissions-policy'

# 3) Debug mati: akses URL yang error -> harus halaman 500 generik, BUKAN stack trace
curl.exe -s https://pendekar.dinsos.go.id/profile/tidak-ada-id

# 4) Pastikan tidak ada folder sensitif yang dapat diakses
foreach ($p in '.env','.git/config','storage/logs/laravel.log','database/database.sqlite') {
    "`n$p => " + (curl.exe -s -o NUL -w '%{http_code}' "https://pendekar.dinsos.go.id/$p")
}
```

### 6.5 Catatan Octane (Dockerfile saat ini `php artisan serve`)

Bila produksi beralih ke `php artisan octane:start`, `APP_KEY`/config harus di-cache (`php artisan config:cache`)
dan pastikan ekstensi (`swoole`/`roadrunner`) terpasang di image. Middleware `SecurityHeaders` kompatibel dengan Octane
(tidak menyimpan state di properti).

---

## 7. Test Suite Otomatis (sudah ditambahkan & lulus)

```powershell
php artisan test                          # seluruh suite
php artisan test --filter=Security        # hanya test keamanan
```

| File | Cakupan |
|---|---|
| `tests/Feature/Security/PublicProfileVisibilityTest.php` | Published=200, Draft=404, Archived=404, admin=404, slug tanpa ID=404 |
| `tests/Feature/Security/PanelAuthorizationTest.php` | tamu→login, klien≠/admin (403), admin≠/klien (403), klien=200 di /klien |
| `tests/Feature/Security/SecurityHeadersTest.php` | header wajib ada di `/`, `/admin/login`, `/klien/login`; `X-Powered-By` tidak bocor |

Hasil audit terakhir:

```
Tests:    14 passed (34 assertions)
Duration: 2.74s
```

**Perubahan pendukung:** `phpunit.xml` sekarang memaksa `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:`.
Sebelumnya test berjalan di database dev (berisiko terhapus bila memakai `RefreshDatabase`).

---

## 8. Checklist Verifikasi Setelah Setiap Fase

```
[ ] php artisan test ............................ hijau
[ ] / (beranda) ................................ 200, daftar peserta Published tetap tampil
[ ] /profile/{slug} user Published ............. 200
[ ] /profile/{slug} user Draft ................. 404
[ ] /admin (tamu) .............................. redirect /admin/login
[ ] /klien (tamu) .............................. redirect /klien/login
[ ] login admin -> /admin/users ................ 200, CRUD user normal
[ ] login klien -> /klien/pesertas ............ 200 (dan hanya data miliknya, jika Opsi A)
[ ] import 1 baris -> data muncul ............. tanpa error, status sesuai kebijakan
[ ] Set-Cookie ................................. secure; httponly; samesite=lax
[ ] header respons ............................. XFO/nosniff/Referrer-Policy ada
[ ] storage/logs/laravel.log ................... tidak ada DecryptException / MassAssignmentException
```

**Rollback per fase**

| Fase | Cara rollback |
|---|---|
| FASE 0 | Kembalikan file dari `_backups/scripts` (hanya untuk lokal; jangan di produksi) |
| FASE 1 | Kembalikan `APP_KEY` + `APP_PREVIOUS_KEYS` dari backup `.env`, `php artisan config:clear` |
| FASE 2 | `git revert` commit; migrasi `created_by` di-rollback (`php artisan migrate:rollback --step=1`) |
| FASE 3 | `git revert` commit import; file lama di `storage/app/public/imports` tidak terpengaruh |
| FASE 4 | Ubah `.env` kembali, `php artisan config:clear` |

---

## 9. Risiko Residual & Backlog

| Prioritas | Item | Catatan |
|---|---|---|
| Tinggi | Belum ada 2FA untuk `/admin` | Filament v3 belum punya 2FA bawaan; opsi: plugin pihak ketiga atau TOTP gate di `canAccessPanel()` |
| Tinggi | `.env` lama masih ada di riwayat Git (initial commit & `1407c68d`) | Rotasi kredensial (FASE 0/1). Purge history opsional: `git filter-repo --path .env --invert-paths` (menulis ulang hash, butuh koordinasi tim) |
| Sedang | Belum ada `passwordReset()` & verifikasi email di panel | Tambahkan `->passwordReset()` pada kedua panel bila SMTP siap |
| Sedang | Tidak ada Activity Log (spatie/laravel-activitylog) | Jejak audit perubahan data peserta |
| Rendah | Typo `$gender22` di `HomeController.php:57` | Filter gender menghasilkan error 500 (bukan celah, tapi bug). Perbaiki di commit terpisah: `$gender22` → `$gender` |
| Rendah | `.DS_Store` di `public/` dan `tests/` | Hapus + `.gitignore` |
| Rendah | `/up` healthcheck publik | Boleh dibiarkan (tanpa data sensitif) atau batasi di web server |

---

## 10. Lampiran — Perintah Audit yang Dipakai

```powershell
# Struktur & model
Get-ChildItem app\Models -File | Select-Object Name
Select-String -Path app\**\*.php -Pattern 'Model::unguard|request->all\(\)|->fill\('

# Skema & kepemilikan data
php artisan tinker --execute="echo implode(',', Illuminate\Support\Facades\Schema::getColumnListing('users'));"

# Kredensial lemah (cek tanpa mencetak hash)
php artisan tinker --execute="echo Illuminate\Support\Facades\Hash::check('admin123', App\Models\User::where('email','admin@admin.com')->value('password')) ? 'LEMAH' : 'ok';"

# Kebocoran profil draft (butuh user draft)
curl.exe -s -o NUL -w "%{http_code}`n" http://127.0.0.1:8099/profile/apa-saja-<id>

# Header & cookie
curl.exe -s -D - -o NUL http://127.0.0.1:8099/admin/login

# Dependensi rentan
composer audit --locked --no-interaction

# Route tanpa auth (harus hanya halaman publik)
php artisan route:list --except-vendor
```










---

## 11. Catatan Eksekusi Remediasi — 10 November 2026 (rilis `v13.1.0-secured`)

Bagian ini mencatat apa yang **benar-benar dieksekusi** (bukan rencana), beserta bukti verifikasi dan cara rollback.

### 11.1 FASE 0 — Triage kritis (SELESAI)

| # | Tindakan | Detail | Bukti |
|---|----------|--------|-------|
| 1 | Skrip backdoor dikeluarkan dari webroot | `update_users.php`, `check_user.php`, `scratch_schema.php`, `test_import.php` dipindah ke `_backups/scripts/` | `Get-ChildItem *.php` di root = kosong |
| 2 | Skrip di-untrack dari Git | `git rm --cached update_users.php check_user.php` | `git status` menampilkan `deleted: update_users.php` |
| 3 | `.gitignore` diperkuat | tambah `/_backups/` (sebelumnya sudah ada `*.bak`, `.env`) | file `.gitignore` baris 10-11 |
| 4 | Backup berbahaya dihapus | `.env.bak` (berisi `APP_KEY` lama) & `composer.lock.bak` | `Test-Path` = False |
| 5 | Password admin dirotasi | `admin@admin.com`, 20 karakter acak via `Str::password(20)`; `Hash::check('admin123', ...)` = **false** | probe `cek_admin123: sudah tidak berlaku` |
| 6 | `.env.example` dibersihkan | `APP_KEY` lama dikosongkan, tambah `APP_PREVIOUS_KEYS=` | `.env.example` baris 3-4 |

> ⚠️ Password admin baru **tidak** disimpan di repo/dokumen. Simpan di password manager, lalu ganti lagi setelah login pertama bila perlu.
> Perintah `git rm --cached` **belum** di-commit saat catatan ini ditulis — commit ada di §11.8.

### 11.2 FASE 1 — Rotasi `APP_KEY` + `APP_PREVIOUS_KEYS` (SELESAI)

`config/app.php` sudah memiliki `previous_keys => env('APP_PREVIOUS_KEYS')` (tidak perlu diubah).

Prosedur yang dijalankan:

1. Backup `.env` → `_backups/env/.env.before-key-rotation` (di luar Git).
2. `php artisan key:generate --show` → kunci baru, ditulis ke `APP_KEY`.
3. Kunci lama (51 karakter `base64:...`) ditulis ke `APP_PREVIOUS_KEYS`.
4. `php artisan config:clear`.

Bukti verifikasi (dijalankan langsung terhadap aplikasi):

```text
previous_keys decrypt: OK        # payload dienkripsi kunci lama, didekripsi kunci baru
key_berubah=ya
roundtrip_key_baru=OK
dekripsi_data_lama=OK
previous_keys=1
```

Dampak yang **diharapkan**: seluruh sesi & cookie `remember-me` lama menjadi tidak valid (semua pengguna login ulang). Tidak ada data bisnis yang hilang karena repo tidak memakai cast `encrypted` (lihat §3.1).

`.env` tidak berisi duplikasi key (diverifikasi); `APP_KEY`/`APP_PREVIOUS_KEYS` masing-masing muncul satu kali.

### 11.3 FASE 2 — Opsi A: kepemilikan data peserta (SELESAI)

**a) Migrasi** `database/migrations/2026_11_10_090000_add_created_by_to_users_table.php`

- Menambah `users.created_by` (`unsignedBigInteger`, nullable) + index.
- Foreign key `→ users.id` `nullOnDelete()` ditambahkan hanya untuk driver non-SQLite (SQLite tidak mendukung `ALTER TABLE ... ADD CONSTRAINT`).
- **Backfill**: seluruh `user_type = 'client'` yang belum punya pemilik ditetapkan ke **admin pertama**. Alasan: kepemilikan tidak bisa direkonstruksi dari data lama, sehingga dipilih default paling aman (hanya admin yang melihat; tidak ada klien yang mewarisi data klien lain).
- Status di DB dev saat eksekusi: `admin=1, client=0` → backfill tidak mengubah baris apa pun.

Bila nanti perlu memindahkan data lama ke klien tertentu:

```php
// php artisan tinker
App\Models\User::whereIn('id', [10, 11, 12])->update(['created_by' => 5]);
```

**b) Scoping panel `/klien`** — `app/Filament/Client/Resources/PesertaResource.php`

```php
$query = parent::getEloquentQuery()->where('user_type', 'client');

if (auth()->check() && auth()->user()->user_type === 'client') {
    $query->where('created_by', auth()->id());
}
```

Karena Filament memakai query yang sama untuk me-resolve record pada halaman edit, ID peserta milik klien lain otomatis menghasilkan **404** (bukan 403 — tidak membocorkan keberadaan data).

**c) Titik pembuatan data menyimpan pemilik**

- `PesertaResource\Pages\CreatePeserta::mutateFormDataBeforeCreate()` → `created_by = auth()->id()`.
- `ImportService::importData(..., ?int $createdBy)` → `User::create([... 'created_by' => $createdBy])`; halaman Import (admin & klien) mengirim `auth()->id()`.

**d) `Model::unguard()` global dihapus** — `app/Providers/AppServiceProvider.php`

- `Model::unguard()` dihapus; kini setiap model memakai `$fillable` eksplisit:
  - `User`: `name, email, email_verified_at, password, user_type, status, created_by`
  - `UserProfile`: kolom profil (17 field) — **`user_id` sengaja tidak fillable**
  - `UserExperience`: `experience_year, experience_description, experience_job_name, sort_order, is_current_job` — **`user_id` tidak fillable**
  - `MasterCity`, `MasterEducationDegree`, `MasterEthnicGroup`, `MasterReligion`, `MasterSkill`, `MasterTraining`: `title`
- Konsekuensi yang ditangani agar tidak ada regresi:
  - `ImportService` membuat profil & pengalaman lewat **relasi** (`$user->userProfile()->create()`, `$user->userExperiences()->create()`), bukan `user_id` mass-assignment.
  - `UserProfileEditPage::save()` diubah dari `UserProfile::updateOrCreate(['user_id' => ...], $data)` menjadi ambil/buat instance lewat relasi lalu `fill($data)->save()`.

Bukti: `Model::isUnguarded()` = **false**; `MassAssignmentTest` & `ProfileOwnershipTest` lulus.

### 11.4 FASE 3 — Hardening alur import (SELESAI)

| Item | Sebelum | Sesudah |
|------|---------|---------|
| Password peserta hasil import | hardcoded `password123` | password acak `Str::password(config('pendekar.import.password_length'))`, atau "kata sandi awal" yang diverifikasi terhadap `IMPORT_PASSWORD_HASH` |
| Batas baris | tidak ada | maksimum `config('pendekar.import.max_rows')` (default **2000**), melempar exception sebelum validasi |
| Lokasi file import | dibaca dari `storage_path('app/public/...')` (bisa terekspos bila `FILESYSTEM_DISK=public`) | disk `local` + `visibility('private')` + nama file diacak, dibaca via `Storage::disk('local')->path()` |
| Parser spreadsheet | `IOFactory::load()` (memuat style/objek) | `createReaderForFile()` + `setReadDataOnly(true)` |
| Jejak audit | tidak ada | `Log::info('import.peserta', [actor_id, rows, pakai_kata_sandi_awal])` |
| File template unduhan | ditulis ke `storage/app/public/` | ditulis ke `storage/app/` lalu `deleteFileAfterSend()` |

Verifikasi fungsi kata sandi awal (dijalankan langsung):

```text
cocok=YA            # Pdk#... (nilai di .env) diterima
salah=ditolak       # password123 ditolak
kosong=boleh        # tanpa kata sandi awal -> password acak
```

Catatan perilaku (disengaja): status peserta hasil import **tetap** `published` seperti sebelumnya — patch ini tidak mengubah alur bisnis publikasi.

### 11.5 Konfigurasi `.env` baru (FASE 3)

Tambahkan pada `.env` produksi/staging/dev:

```dotenv
# Batas & keamanan alur import
IMPORT_MAX_ROWS=2000
IMPORT_PASSWORD_LENGTH=32
IMPORT_PASSWORD_HASH='$2y$12$...'   # hasil: php artisan tinker --execute="echo Hash::make('KATA-SANDI-IMPOR');"
```

- `IMPORT_PASSWORD_HASH` **wajib** diisi bila operator ingin memakai fitur "Kata Sandi Awal" pada form import; bila kosong, field tersebut selalu ditolak (fail-closed) dan peserta mendapat password acak.
- Setelah mengubah `.env`: `php artisan config:clear` (atau `config:cache` pada produksi).

### 11.6 Test suite final

```text
Tests:    28 passed (63 assertions)
```

| File | Cakupan |
|------|---------|
| `tests/Feature/Security/PublicProfileVisibilityTest.php` | profil Draft/Archived/admin tidak dapat diakses publik |
| `tests/Feature/Security/SecurityHeadersTest.php` | header keamanan + versi PHP tidak bocor |
| `tests/Feature/Security/PanelAuthorizationTest.php` | pemisahan panel `/admin` vs `/klien` |
| `tests/Feature/Security/ClientPesertaScopingTest.php` | **FASE 2** — klien hanya melihat/mengedit peserta miliknya (404 untuk milik klien lain & data tanpa pemilik) |
| `tests/Feature/Security/MassAssignmentTest.php` | **FASE 2** — `unguard` nonaktif, atribut asing & `user_id` diabaikan |
| `tests/Feature/Security/ProfileOwnershipTest.php` | **FASE 2** — `user_id` tetap benar lewat relasi; klien tanpa profil tetap bisa membuka halaman ubah profil |
| `tests/Feature/Security/ImportHardeningTest.php` | **FASE 3** — batas 2000 baris, tidak ada `password123`, verifikasi `IMPORT_PASSWORD_HASH`, `created_by` tercatat |

Perintah: `php artisan test` (SQLite in-memory, tidak menyentuh DB dev/produksi).

### 11.7 Rollback per fase

| Fase | Rollback |
|------|----------|
| FASE 0 | `git checkout <commit-sebelumnya> -- .gitignore` lalu `git add update_users.php check_user.php` (bila file memang dibutuhkan); `.env.bak`/`composer.lock.bak` tidak perlu dikembalikan |
| FASE 1 | kembalikan `APP_KEY` dari `_backups/env/.env.before-key-rotation`, kosongkan `APP_PREVIOUS_KEYS`, `php artisan config:clear` |
| FASE 2 | `php artisan migrate:rollback --step=1` (menghapus kolom `created_by`), lalu revert commit; tanpa `created_by`, scoping tidak aktif |
| FASE 2 (`$fillable`) | revert commit `AppServiceProvider` + model = `unguard` kembali aktif |
| FASE 3 | revert commit; set `IMPORT_MAX_ROWS=0` untuk menonaktifkan batas tanpa mengubah kode |

### 11.8 Git — commit & tag

```bash
git add -A
git status                       # pastikan .env & _backups/ TIDAK ikut
git commit -m "security(l13): FASE 0-3 — scoping panel klien, hapus unguard, hardening import, rotasi APP_KEY"
git tag -a v13.1.0-secured -m "Rilis keamanan v13.1.0: FASE 0-3 (Opsi A)"
git push origin feature/upgrade-laravel-13
git push origin v13.1.0-secured
```

### 11.9 Risiko residual setelah rilis ini

1. **Foto profil import** masih memakai `preserveFilenames()` karena pencocokan foto memakai nama file dari Excel (`Nama File Foto`). Bila nama file diacak, fitur pasangan-foto otomatis rusak. Mitigasi lanjutan: simpan foto ke disk privat + sajikan lewat route ber-otorisasi.
2. **Belum ada UI admin untuk memindahkan `created_by`** data lama; sementara lewat tinker (§11.3).
3. **Alur reset password peserta** belum diuji end-to-end untuk peserta hasil import dengan password acak — pastikan `MAIL_*` produksi berfungsi sebelum import massal tanpa kata sandi awal.
4. **`trustProxies(at: '*')`** masih permisif (§6.3) — set `TRUSTED_PROXIES` sesuai IP load balancer.
5. **CSP** sengaja belum diaktifkan (akan memblokir inline script Filament/Livewire/Alpine) — perlu nonce khusus bila ingin diterapkan.
6. **Document root** wajib `public/`; `SecurityHeaders` + `.env` di luar `public/` sudah aman, tetapi salah konfigurasi Apache/Nginx akan mengekspos seluruh proyek.



### 11.10 Insiden pasca-rilis: `.env` hilang -> HTTP 500 (MissingAppKeyException)

**Gejala.** Setelah `v13.1.0-secured` di-commit, halaman `http://127.0.0.1:8000` mengembalikan
`500 Server Error`. `storage/logs/laravel.log` mencatat:

```
production.ERROR: No application encryption key has been specified.
Illuminate\Encryption\MissingAppKeyException at .../EncryptionServiceProvider.php:83
```

**Akar masalah.** File `.env` **tidak ada lagi di root proyek**. Tanpa `.env`, `config('app.key')`
kosong, `APP_ENV` jatuh ke default `production`, dan `APP_DEBUG` ke `false` (karena itu yang tampil
halaman 500 generik, bukan stack trace). Bukti saat kejadian:

```
Get-ChildItem -Force -Filter '.env*'   -> hanya .env.example + _backups/env/.env.before-key-rotation
php artisan tinker --execute="..."     -> env=production keyLen=0 cached=no
```

Penyebab pasti hilangnya file tidak dapat dibuktikan dari log (tidak ada perintah yang menghapus
`.env`; kandidat tersangka: antivirus/Windows Defender mengarantina file dotfile, atau penulisan
file yang terinterupsi saat sesi sebelumnya). Karena itu mitigasinya bersifat pencegahan
(salinan cadangan berkala), bukan sekadar rekonstruksi.

**Pemulihan yang dilakukan (tanpa menurunkan keamanan).**

1. Rekonstruksi `.env` dari `_backups/env/.env.before-key-rotation` (berisi seluruh variabel
   non-rahasia: `DB_CONNECTION=sqlite`, `SESSION_*`, `MAIL_*`, dll.).
2. `APP_KEY` **tidak** dikembalikan ke kunci lama (kunci lama sudah dianggap bocor di FASE 0/1).
   Dibuat kunci baru `base64:...` (32 byte, len 51) pada 2026-11-10 15:57.
3. Kunci lama dipasang sebagai `APP_PREVIOUS_KEYS` sehingga payload lama tetap dapat didekripsi.
4. `IMPORT_MAX_ROWS`, `IMPORT_PASSWORD_LENGTH`, `IMPORT_PASSWORD_HASH` ditulis ulang (hash bcrypt
   baru, cost 12, untuk kata sandi awal import yang baru).
5. Salinan `.env` pasca-pemulihan disimpan di `_backups/env/.env.restored-<timestamp>`
   (direktori `/_backups/` sudah masuk `.gitignore`, jadi tidak pernah ter-commit).

**Verifikasi setelah pemulihan.**

```
env=local                     debug=true
keyLen=51                     prevKeys=1
cipher=AES-256-CBC            db=sqlite
users=1 (admin=1, client=0)   created_by_col=yes
prevDecrypt=probe-lama        <- payload ber-kunci lama tetap terbaca via APP_PREVIOUS_KEYS
importHashCheck=ok            unguarded=false
```

```
Invoke-WebRequest http://127.0.0.1:8000        -> 200 (19.540 byte)
Invoke-WebRequest http://127.0.0.1:8000/admin  -> 200
php artisan test                               -> 28 passed (63 assertions)
```

**Dampak.** Tidak ada data bisnis yang hilang (tidak ada kolom ber-cast `encrypted`; kata sandi
memakai bcrypt, bukan enkripsi). Yang terpengaruh hanya **sesi login** yang terbentuk dengan kunci
sementara yang hilang -- semua pengguna perlu login ulang. Pesan error bersifat fail-closed:
tanpa `APP_KEY`, aplikasi menolak melayani request (tidak ada data yang disajikan dengan kunci salah).

**Pencegahan (checklist operasional).**

- Simpan salinan `.env` produksi di tempat aman (password manager / secret manager), bukan hanya di server.
- Rujuk `_backups/env/` sebagai cadangan lokal; jangan pernah commit (sudah di-ignore).
- Bila memakai hosting/CI: `APP_KEY` di-inject lewat environment variabel, bukan file, agar
  hilangnya file tidak mematikan aplikasi.
- Setelah memulihkan `APP_KEY`: `php artisan config:clear` (dan `config:cache` bila memakai cache config),
  lalu verifikasi `keyLen=44/51` serta lakukan login uji.

