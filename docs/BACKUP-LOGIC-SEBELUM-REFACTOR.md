# Backup Logic & Fitur Sebelum Refactor Panel Klien

> **Status:** SNAPSHOT kondisi kode SEBELUM refactor (dokumen ini tidak mengubah perilaku aplikasi).
> **Dibuat:** 12 November 2026
> **Repo:** `c:\laragon\www\Dinsos\pendekar1721` — branch `feat/modul-absensi`, HEAD `37bd85f7`
> **Stack terverifikasi:** PHP 8.4.7, Laravel 13.40.0, Filament (panel `admin` + `client`), Livewire, PhpSpreadsheet/PhpWord.
> **Tujuan:** menyimpan perilaku & konfigurasi yang berlaku hari ini agar (a) bisa dibandingkan setelah refactor, (b) bisa dipulihkan bila ada regresi, (c) menjadi bukti bahwa tidak ada fitur yang hilang tanpa disengaja.

---

## 0. Keputusan yang disetujui (D-01 … D-06)

| Kode | Keputusan | Dampak |
|------|-----------|--------|
| D-01 | Status default data hasil import binaan = **Draft** | Binaan hasil import belum bisa login & tidak tampil publik |
| D-02 | Dashboard Klien **hanya menampilkan angka alumni**; daftar alumni opsional via `config('pendekar.alumni.directory_enabled')` (default `false`), tanpa email/telepon | Tidak ada PII di dashboard |
| D-03 | Kelulusan **manual oleh admin** (bukan otomatis dari `PembinaanService`) | Admin memicu pembuatan akun Klien |
| D-04 | `users.email` dibuat **nullable** | Binaan Draft boleh belum punya email |
| D-05 | Jangka pendek tetap **2 koneksi**: `anak` di `semarak`, jembatan `users.anak_id` di koneksi utama | Tidak ada transaksi atomik lintas DB → butuh kompensasi |
| D-06 | Opsi A/B (`created_by` scoping) **dibatalkan** | Klien tidak lagi punya data peserta sendiri |

---

## 1. Metode backup yang dipilih

**Pilihan: salinan fisik ke `docs/backup-pra-refactor/`** (bukan commit/tag git).

Alasan:
1. Working tree branch `feat/modul-absensi` **sudah kotor** dengan perubahan tak terkait refactor ini
   (aset `public/build/*` terhapus, `resources/js/absensi/*`, `resources/views/absensi/*`, `docs/ABSENSI-*.md`, dll).
   Membuat commit/tag sekarang akan mencampur WIP tersebut atau memaksa staging selektif yang berisiko.
2. Salinan fisik tidak menyentuh index git, tidak bisa "hilang" karena `git checkout`, dan isinya bisa dibaca langsung
   tanpa perlu checkout branch lain.
3. Folder salinan berada di `docs/` sehingga tidak pernah di-autoload Composer maupun dipanggil aplikasi.

### 1.1 Daftar file yang disalin (struktur path dipertahankan)

| Path asli | Status rencana |
|-----------|---------------|
| `app/Filament/Client/Pages/ImportPeserta.php` | **DIHAPUS** (Fase 1) |
| `resources/views/filament/client/pages/import-peserta.blade.php` | **DIHAPUS** (Fase 1) |
| `app/Filament/Client/Resources/PesertaResource.php` | **DIHAPUS** (Fase 1) |
| `app/Filament/Client/Resources/PesertaResource/Pages/CreatePeserta.php` | **DIHAPUS** (Fase 1) |
| `app/Filament/Client/Resources/PesertaResource/Pages/EditPeserta.php` | **DIHAPUS** (Fase 1) |
| `app/Filament/Client/Resources/PesertaResource/Pages/ListPesertas.php` | **DIHAPUS** (Fase 1) |
| `app/Filament/Client/Pages/Dashboard.php` | DITULIS ULANG (Fase 0 + Fase 1) |
| `resources/views/filament/client/pages/dashboard.blade.php` | DITULIS ULANG (Fase 0 + Fase 1) |
| `app/Filament/Client/Resources/TrainingResource.php` | DIBUAT READ-ONLY (Fase 1) |
| `app/Filament/Client/Resources/TrainingResource/Pages/ListTrainings.php` | DIBUAT READ-ONLY (Fase 1) |
| `app/Filament/Client/Pages/UserProfileEditPage.php` | DIBATASI ke `auth()->id()` (Fase 1) |
| `app/Models/User.php` | DITAMBAH guard status + relasi `anak()` (Fase 0/2) |
| `app/Http/Controllers/Auth/LoginController.php` | DITAMBAH penolakan `Draft` (Fase 0) |
| `config/pendekar.php` | DITAMBAH `alumni.directory_enabled` (Fase 1) |
| `tests/Feature/Security/ClientPesertaScopingTest.php` | DIGANTI `ClientPanelMenuTest` (Fase 1) |

### 1.2 Cara restore satu file

```powershell
Copy-Item -LiteralPath 'docs\backup-pra-refactor\app\Filament\Client\Resources\PesertaResource.php' `
          -Destination 'app\Filament\Client\Resources\PesertaResource.php' -Force
```

### 1.3 Cara restore seluruh panel Klien lama

```powershell
Copy-Item -Recurse -Force 'docs\backup-pra-refactor\app\Filament\Client\*' 'app\Filament\Client\'
Copy-Item -Force 'docs\backup-pra-refactor\resources\views\filament\client\pages\*' 'resources\views\filament\client\pages\'
```

> Catatan: restore penuh juga butuh mengembalikan `app/Models/User.php`, `LoginController.php`, dan `config/pendekar.php`
> dari folder yang sama, karena keduanya ikut diubah di Fase 0–1.

---

## 2. Daftar route saat ini (sebelum refactor)

Diambil dengan `php artisan route:list --path=<prefix> --json` pada commit `37bd85f7`.

### 2.1 Prefix `/admin` — 15 route

| Method | URI | Name |
|--------|-----|------|
| GET/HEAD | `admin` | `filament.admin.pages.dashboard` |
| GET/HEAD | `admin/anak` | `filament.admin.resources.anak.index` |
| GET/HEAD | `admin/import-data` | `filament.admin.pages.import-data` |
| GET/HEAD | `admin/kegiatan` | `filament.admin.resources.kegiatan.index` |
| POST | `admin/logout` | `filament.admin.auth.logout` |
| GET/HEAD | `admin/master-cities` | `filament.admin.resources.master-cities.index` |
| GET/HEAD | `admin/master-education-degrees` | `filament.admin.resources.master-education-degrees.index` |
| GET/HEAD | `admin/master-ethnic-groups` | `filament.admin.resources.master-ethnic-groups.index` |
| GET/HEAD | `admin/master-religions` | `filament.admin.resources.master-religions.index` |
| GET/HEAD | `admin/master-skills` | `filament.admin.resources.master-skills.index` |
| GET/HEAD | `admin/master-trainings` | `filament.admin.resources.master-trainings.index` |
| GET/HEAD | `admin/users` | `filament.admin.resources.users.index` |
| GET/HEAD | `admin/users/create` | `filament.admin.resources.users.create` |
| GET/HEAD | `admin/users/{record}` | `filament.admin.resources.users.view` |
| GET/HEAD | `admin/users/{record}/edit` | `filament.admin.resources.users.edit` |

Catatan: resource `anak`, `kegiatan`, dan master data memakai halaman **Manage** (Create/Edit berupa modal Livewire),
sehingga tidak punya route `create`/`{record}/edit` terpisah. Hanya `UserResource` yang punya route view/edit.

**Middleware panel admin** (urut):
`Filament\Http\Middleware\SetUpPanel:admin` → `EncryptCookies` → `AddQueuedCookiesToResponse` → `StartSession`
→ `ShareErrorsFromSession` → **`App\Http\Middleware\AuthenticatePanel`** → `AuthenticateSession` → `PreventRequestForgery`
→ `SubstituteBindings` → `DisableBladeIconComponents` → `DispatchServingFilamentEvent`.

### 2.2 Prefix `/klien` — 8 route

| Method | URI | Name |
|--------|-----|------|
| GET/HEAD | `klien` | `filament.client.pages.dashboard` |
| GET/HEAD | `klien/import-peserta` | `filament.client.pages.import-peserta` |
| POST | `klien/logout` | `filament.client.auth.logout` |
| GET/HEAD | `klien/pesertas` | `filament.client.resources.pesertas.index` |
| GET/HEAD | `klien/pesertas/create` | `filament.client.resources.pesertas.create` |
| GET/HEAD | `klien/pesertas/{record}/edit` | `filament.client.resources.pesertas.edit` |
| GET/HEAD | `klien/trainings` | `filament.client.resources.trainings.index` |
| GET/HEAD | `klien/user-profile-edit-page` | `filament.client.pages.user-profile-edit-page` |

**Middleware panel klien**: identik dengan admin, hanya `SetUpPanel:client`.

### 2.3 Prefix `/absensi` — 8 route

| Method | URI | Name | Middleware tambahan |
|--------|-----|------|---------------------|
| GET/HEAD | `absensi` | `absensi.dashboard` | `web > auth > EnsureAbsensiAdmin` |
| GET/HEAD | `absensi/anak` | `absensi.anak` | idem |
| GET/HEAD | `absensi/api` | `absensi.api` | + `ThrottleRequests:absensi-api` |
| POST | `absensi/api` | `absensi.api.write` | + `ThrottleRequests:absensi-writes` |
| GET/HEAD | `absensi/input` | `absensi.input` | idem |
| GET/HEAD | `absensi/pengaturan` | `absensi.pengaturan` | idem |
| GET/HEAD | `absensi/rapor` | `absensi.rapor` | idem |
| GET/HEAD | `absensi/summary` | `absensi.summary` | idem |

### 2.4 Route publik & login (di luar prefix di atas)

| Method | URI | Name | Middleware |
|--------|-----|------|-----------|
| GET/HEAD | `/` | `home` | `web` |
| GET/HEAD | `login` | `login` | `web` |
| POST | `login` | `login.store` | `web > throttle:login` |
| POST | `logout` | `logout` | `web > auth` |
| GET/HEAD | `/profile/{slug}` | `front.user.detail` | `web` |
| GET/HEAD | `/program/menjahit` | `front.program.sewing` | `web` |
| GET/HEAD | `/program/bimbingan-sosial` | `front.program.social-guidance` | `web` |
| GET/HEAD | `/program/{program}` | `front.program.detail` | `web` (whereIn: montir-motor, montir-mobil, tata-boga) |

---

## 3. Fitur panel Klien saat ini (`/klien`)

### 3.1 Menu yang tampil (urutan `navigationSort`)

| Sort | Menu | Tipe | Kelas |
|------|------|------|-------|
| — | Dashboard Klien | Page | `App\Filament\Client\Pages\Dashboard` |
| 2 | Data Peserta | Resource | `App\Filament\Client\Resources\PesertaResource` |
| 3 | Import Data | Page | `App\Filament\Client\Pages\ImportPeserta` |
| 4 | Data Pelatihan | Resource | `App\Filament\Client\Resources\TrainingResource` |
| — | Ubah Profil | Page | `App\Filament\Client\Pages\UserProfileEditPage` |

Tidak ada `navigationGroup` di panel Klien; semua menu berada di grup default (tanpa judul).

### 3.2 Siapa yang boleh akses

1. `AuthenticatePanel` (turunan `Filament\Http\Middleware\Authenticate`) → tamu diarahkan ke `route('login')`.
2. `User::canAccessPanel(Panel $panel)` → `'client' => $this->isClient()` (`user_type === 'client'`). **Status tidak diperiksa.**
3. `Dashboard::canAccess()`, `ImportPeserta::canAccess()` → cek ulang `auth()->user()->user_type === 'client'`.
4. `PesertaResource` / `TrainingResource` / `UserProfileEditPage` **tidak** override `canAccess()` → cukup lolos poin 2.

Konsekuensi hari ini: user `user_type=client` dengan `status=draft` **tetap bisa masuk panel** (lihat §5).

### 3.3 Alur CRUD Peserta (PesertaResource)

- Model: `App\Models\User`; query dasar `where('user_type','client')`.
- Scoping (FASE 2 / Opsi A): bila `auth()->user()->user_type === 'client'` → `+ where('created_by', auth()->id())`.
  Filament memakai query yang sama untuk resolve record, sehingga edit ID milik klien lain = 404.
- **Create** (`CreatePeserta::mutateFormDataBeforeCreate`): memaksa `user_type='client'`, `status='published'`, `created_by=auth()->id()`.
  → Peserta buatan klien **langsung Published** (langsung bisa login, langsung tampil di profil publik).
- **Form** (3 section):
  1. *Informasi Akun*: `name` (required, max 255), `email` (required, email, `unique(ignoreRecord:true)`, max 255),
     `password` (required saat create; `dehydrateStateUsing(Hash::make)`, `dehydrated(fn($s)=>filled($s))`).
  2. *Profil Lengkap* (relasi `userProfile`): `gender` (L/P), `birth_place`, `birth_date`, `ktp_city_id`,
     `education_degree_id`, `hire_status` (Tersedia/Tidak, default `'1'`), `profile_picture_path` (upload gambar, disk `public`, dir `profile_pictures`).
  3. *Keterampilan & Penempatan*: `userTrainings` (multiple), `userWorkLocations` (multiple).
- **Table**: foto (circular, default avatar), nama (searchable/sortable), email (searchable), gender (badge), pendidikan,
  ikon `hire_status`. Aksi: hanya `EditAction`. Tidak ada filter & bulk action.
- **Pages**: `index` (`ListPesertas` + tombol header "Tambah Peserta Manual"), `create`, `edit`.
  `EditPeserta` tidak punya header action (tanpa delete).

### 3.4 Alur Import Peserta (`ImportPeserta` + `ImportService`)

Halaman `klien/import-peserta` (slug `import-peserta`, judul "Import Data Peserta"), form Livewire:

1. **Upload file** (xlsx/xls/csv/docx), wajib, max 5 MB, disk `local` → `storage/app/imports`, visibility private.
2. **Upload foto** (opsional, banyak file, jpg/png, max 2 MB), disk `public` → `profile_pictures`, `preserveFilenames()`.
   Nama file foto harus sama dengan kolom "Nama File Foto" di spreadsheet.
3. **Kata sandi awal** (opsional) — harus cocok dengan `IMPORT_PASSWORD_HASH` di `.env`
   (diverifikasi `ImportService::initialPasswordMatches()`; kosong = boleh).
4. **Preview** (`processPreview`): `ImportService::parseAndValidate($filePath, $extension)` →
   hasil `['valid'=>[], 'errors'=>[], 'duplicates'=>[], 'total'=>n]`.
5. **Konfirmasi** (`confirmImport`): tolak bila masih ada error / tidak ada baris valid; verifikasi kata sandi awal;
   `ImportService::importData($valid, $photos, auth()->id(), $importPassword)`.

**Aturan parsing** (`ImportService`):
- Excel/CSV: header baris 1 dinormalisasi (`strtolower`, trim, buang `*`); `setReadDataOnly(true)`;
  baris kosong dilewati; nomor baris asli disimpan di `_row_number`.
- DOCX: tabel pertama dibaca, header sama.
- Batas baris: `config('pendekar.import.max_rows')` default **2000** → lebih dari itu melempar `Exception`.

**Validasi per baris** (`validateRows`):

| # | Kolom (header) | Aturan | Field hasil |
|---|----------------|--------|-------------|
| 1 | `Nama` | wajib | `name` |
| 2 | `Email` | wajib, `FILTER_VALIDATE_EMAIL`, unik terhadap `users.email` (case-insensitive); duplikat → `duplicates`, baris dilewati | `email` |
| 3 | `Jenis Kelamin` | harus `L`/`P` (kosong = null) | `gender` |
| 4 | `Tempat Lahir` | bebas | `birth_place` |
| 5 | `Tanggal Lahir` | numerik = serial Excel (`($v-25569)*86400`), selain itu `strtotime` | `birth_date` |
| 6 | `Alamat KTP` | bebas | `ktp_address` |
| 7 | `Kota KTP` | harus ada di `master_cities.title` (case-insensitive) | `ktp_city_id` |
| 8 | `Pendidikan` | harus ada di `master_education_degrees.title` | `education_degree_id` |
| 9 | `Pelatihan` | dipisah koma; tiap item harus ada di `master_trainings.title` | `trainings[]` |
| 10 | `Penempatan Wilayah` | dipisah koma; tiap item harus ada di `master_cities.title` | `work_locations[]` |
| 11 | `Status Pekerjaan` | `sedang bekerja`/`1` → true | `is_current_job` |
| 12 | `Deskripsi` | bebas | `description` |
| 13 | `Status Profil` | `tersedia`/`1` → `'1'`, lainnya `'0'` | `hire_status` |
| 14 | `Nama File Foto` | dicocokkan ke basename file foto yang diunggah | `photo_filename` |

Template unduhan (xlsx & docx) memakai 14 header di atas dengan tanda `*` pada Nama/Email/Jenis Kelamin.

**Penulisan data** (`importData`), satu `DB::beginTransaction()` per batch:
- Password: `$initialPassword` bila diisi, jika tidak `Str::password(config('pendekar.import.password_length', 32))`
  (**tidak pernah ditampilkan** → user tanpa kata sandi awal tidak bisa login sampai reset).
- `User::create([... 'user_type'=>'client', 'status'=>'published', 'created_by'=>$createdBy])` → **langsung Published** (F-05).
- `userProfile()->create([...])`, `userTrainings()->attach()`, `userWorkLocations()->attach()`,
  `userExperiences()->create()` bila `is_current_job`.
- Sukses: `Log::info('import.peserta', ['actor_id','rows','pakai_kata_sandi_awal'])`.
- Gagal: `DB::rollBack()` + `Log::error` + rethrow.

### 3.5 Data Pelatihan (TrainingResource) — sudah read-only

- Model `MasterTraining`; hanya halaman `index` (`ListTrainings`), tanpa create/edit/delete.
- Table: `title` (searchable/sortable), `created_at` (dateTime, sortable). Tanpa action & bulk action.
- Artinya menu ini **sudah** sesuai target; Fase 1 hanya menambah `canCreate()/canEdit()/canDelete() = false` eksplisit.

### 3.6 Ubah Profil (UserProfileEditPage)

- Mengedit `auth()->user()->userProfile` (relasi `hasOne` dengan `withDefault()`), bukan record orang lain.
- Field yang tersedia (`statePath('data')`, `model($this->userProfile)`):
  `description`, `birth_place`, `birth_date` (dipecah `birth_date_day/month/year`), `gender`, `body_weight`, `body_height`,
  `ktp_address`, `ktp_city_id`, `domisili_address`, `domisili_city_id`, `education_degree_id`, `religion_id`,
  `ethnic_group_id`, `skck_status`, `surat_kesehatan_status`, `profile_picture_path` (3:4, 675x900),
  repeater `work_experiences` (`experience_year`, `experience_job_name`, `experience_description`, `is_current_job`, `sort_order`).
- `save()`: menyusun ulang `birth_date` dari 3 select, menyimpan profil, lalu **hapus semua** `UserExperience` milik user
  dan membuat ulang dari repeater (`UserExperience::query()->whereBelongsTo(auth()->user())->delete()`).
- Satu-satunya tempat di panel Klien yang **sudah** benar scoping-nya (memakai `auth()`), sehingga dipertahankan.

### 3.7 Dashboard Klien (kondisi hari ini — sumber F-01)

- `Dashboard::getPesertaTerbaru()`: `User::where('user_type','client')->orderBy('created_at','desc')->limit(5)->get()`
  → **tanpa filter status maupun `created_by`**; blade menampilkan `name` + `email` + `hire_status` orang lain.
- `Dashboard::getStatistik()`: `total_peserta` = seluruh user `client`; `total_pelatihan` = jumlah `MasterTraining`;
  `tersedia` = user client yang profilnya `hire_status='1'`.
- Blade juga memuat blok "Aksi Cepat" (Tambah Peserta Manual, Import Data) dan info sistem.

---

## 4. Fitur panel Admin saat ini (`/admin`)

### 4.1 Resource & Page

| Menu | Kelas | Halaman | Catatan |
|------|-------|---------|---------|
| Dashboard | `Filament\Pages\Dashboard` | index | widget `AccountWidget` saja |
| Data Anak (SEMARAK) | `App\Filament\Resources\AnakResource` | `index` (Manage) | CRUD modal, model `Modules\Absensi\Models\Anak` (koneksi `semarak`) |
| Kegiatan | `App\Filament\Resources\KegiatanResource` | `index` (Manage) | modal CRUD |
| Manajemen Klien → Import Data | `App\Filament\Pages\ImportData` | `admin/import-data` | mirip `ImportPeserta` versi admin |
| Master Kota | `App\Filament\Resources\MasterCityResource` | `index` (Manage) | modal CRUD |
| Master Pendidikan | `App\Filament\Resources\MasterEducationDegreeResource` | `index` (Manage) | modal CRUD |
| Master Suku | `App\Filament\Resources\MasterEthnicGroupResource` | `index` (Manage) | modal CRUD |
| Master Agama | `App\Filament\Resources\MasterReligionResource` | `index` (Manage) | modal CRUD |
| Master Keterampilan | `App\Filament\Resources\MasterSkillResource` | `index` (Manage) | modal CRUD |
| Master Pelatihan | `App\Filament\Resources\MasterTrainingResource` | `index` (Manage) | modal CRUD |
| Manajemen Klien → Data Klien | `App\Filament\Resources\UserResource` | index/create/view/edit | resource user lengkap (tanpa `navigationGroup`) |

### 4.2 `ImportData` (admin)

- `canAccess()`: `auth()->user()?->user_type === 'admin'` (admin_absensi **tidak** boleh).
- Slug `import-data`, judul "Import Data Klien", `navigationGroup = 'Manajemen Klien'`, sort 100.
- Form & alur identik dengan `ImportPeserta` (file + foto + kata sandi awal → preview → konfirmasi),
  memakai `ImportService` yang sama dan `created_by = auth()->id()`.
- Menyediakan unduhan template Excel (`downloadExcelTemplate`) dan Word (`downloadWordTemplate`) dengan 14 kolom
  (§3.4) dan satu baris contoh (Siti Aminah). File template sementara ditulis ke `storage/app/` lalu dihapus setelah dikirim.
- Hasil import: **semua** baris menjadi `user_type='client'`, `status='published'`.

### 4.3 `ImportService` (dipakai admin & klien)

Lihat §3.4 untuk detail kolom/validasi/penulisan. Poin penting untuk refactor:
- Hanya ada **satu** jalur tulis (`importData()`), tidak ada parameter `mode`/`status`.
- Duplikat hanya dideteksi lewat `email` (tidak ada dedup by nama/asal/angkatan).
- Password acak (`Str::password`) dibuat sekali per batch dan **tidak dikembalikan** ke pemanggil.
- `created_by` hanya `auth()->id()`; `User` mass-assignable mencakup `created_by` (lihat `MassAssignmentTest`).

### 4.4 `UserResource` (admin)

- Form bersarang `Grid`/`Section`: akun (`name`, `email`, `password`, `password_confirmation`, `user_type`, `status`),
  profil (`description`, `birth_place`, tanggal lahir terpisah, `gender`, `body_weight`, `body_height`, `ktp_address`,
  `ktp_city_id`, `domisili_address`, `domisili_city_id`, `education_degree_id`, `religion_id`, `ethnic_group_id`,
  `skck_status`, `surat_kesehatan_status`), repeater `work_experiences`, dan `profile_picture_path`.
- Table: `name`, `user_type`, `status`, dsb. Aksi per baris: **`ViewAction` + `EditAction`** (tidak ada delete per baris),
  bulk action: `DeleteBulkAction`.
- **Tidak** ada aksi reset password khusus, tidak ada kolom `anak_id`/`graduated_at`, tidak ada guard hapus untuk akun Klien.
- Password pada form create wajib; pada edit hanya diubah bila diisi (`dehydrated` + `Hash::make`).

---

## 5. Logic otorisasi saat ini

### 5.1 `User::canAccessPanel(Panel $panel)`

```php
return match ($panel->getId()) {
    'admin'  => $this->isAdmin(),   // user_type === 'admin'
    'client' => $this->isClient(),  // user_type === 'client'
    default  => false,
};
```

- **Tidak memeriksa `status`.** User Draft/Archived tetap lolos ke panel sesuai `user_type`.
- `admin_absensi` hanya boleh modul `/absensi` (tidak punya panel Filament).
- `UserStatus`: `draft` ("Tidak Aktif (Draft)"), `published` ("Aktif"), `archived` ("Diarsipkan"); kolom `status` **nullable**.

### 5.2 `LoginController::store()` — cabang penolakan

Urutan saat ini:
1. `validate` email+password, lower-case email, ambil `User::where('email', $email)->first()`.
2. `kredensialValid()` — verifikasi hash dengan timing-safe dummy hash bila email tidak ada; `RuntimeException` (hash rusak) → gagal.
3. `match (true)`:
   - `status === Archived` → "Akun Anda sudah diarsipkan dan tidak dapat digunakan. Silakan hubungi administrator."
   - `user_type` bukan admin/admin_absensi/client → "Akun Anda belum memiliki hak akses ke dashboard. Silakan hubungi administrator."
   - `default` → `null` (diterima). **`Draft` lolos di sini.**
4. `Hash::needsRehash()` → perbarui hash; `Auth::login()`, `session()->regenerate()`, redirect `dashboardUrl()`.

Semua penolakan mencatat `Log::warning('Percobaan login gagal', [email, ip, user_agent, status_akun])` (tanpa password).

### 5.3 Middleware terkait

| Middleware | Fungsi |
|------------|--------|
| `App\Http\Middleware\AuthenticatePanel` | Turunan `Filament\Http\Middleware\Authenticate`; `redirectTo()` = `route('login')` (bukan `/admin/login`) |
| `App\Modules\Absensi\Http\Middleware\EnsureAbsensiAdmin` | `canAccessAbsensi()` (= admin / admin_absensi); 403 JSON bila `expectsJson()` |
| `Illuminate\Auth\Middleware\Authenticate` | Dipakai grup `web` di `routes/absensi.php` |
| `throttle:login` | Rate limit login (limiternya di `AppServiceProvider`) |
| `throttle:absensi-api`, `throttle:absensi-writes` | Rate limit endpoint absensi |

### 5.4 Tes yang mengunci perilaku ini

- `tests/Feature/Security/PanelAuthorizationTest.php` — 4 test: admin→`/admin` 200, client→`/klien` 200,
  client ke `/admin` 403, admin ke `/klien` 403.
- `tests/Feature/Auth/UnifiedLoginTest.php` — redirect per role, penolakan akun arsip, dsb.
- `tests/Feature/Security/LoginSecurityTest.php` — timing/rate limit.
- `tests/Feature/Security/ClientPesertaScopingTest.php` — 4 test scoping `created_by` (akan dihapus/diganti).
- `tests/Feature/Security/ProfileOwnershipTest.php`, `MassAssignmentTest.php`, `ImportHardeningTest.php`,
  `PublicProfileVisibilityTest.php` — lihat §8.

---

## 6. Modul Absensi (SEMARAK) — tidak boleh disentuh

### 6.1 Halaman (`PageController`)

| Route | Data awal yang dikirim ke view |
|-------|-------------------------------|
| `absensi` | `angkatanList` = distinct `anak.angkatan` |
| `absensi/input` | `anakList` (order by nama), `kegiatanList` (order by id) |
| `absensi/anak` | `anakList` |
| `absensi/summary` | `anakList` |
| `absensi/rapor` | `anakList` |
| `absensi/pengaturan` | `kegiatanList`, `angkatanList`, `anakList` (id, angkatan) |

### 6.2 API `?action=` (`ApiController`, parity native)

Action tulis (POST): `save_evaluasi`, `save_evaluasi_batch`, `settings_save`, `report_notes_save`,
`anak_store`, `anak_update`, `anak_delete`.
Action baca: `dashboard`, `anak`, `anak_list`, `anak_detail`, `kegiatan`, `evaluasi`, `settings`, `summary`, `report`.
Envelope & status code sengaja meniru native (200/400/404/500; POST ke action GET tetap dilayani).

### 6.3 Alur data

- **Anak** (`App\Modules\Absensi\Models\Anak`, koneksi `semarak`): `id` (`AN-###`) digenerate
  `AnakService::generateId()` (max + 1 per pemanggilan → **tidak concurrency-safe**), validasi wajib:
  `nama`, `jenis_kelamin`, `asal_daerah`, `agama`, `umur > 0`, `angkatan`; `status` default `Aktif`.
  Konstanta yang dipakai: `STATUS_AKTIF`, `STATUS_LULUS`. Hapus anak = hapus `evaluasi` lalu `anak` dalam satu transaksi `semarak`.
- **Kegiatan** (`Kegiatan`): daftar kegiatan harian (jam, nama) yang dipakai form input evaluasi.
- **Evaluasi** (`Evaluasi`): satu baris per anak+kegiatan+tanggal dengan status `sesuai/arahan/telat/tidak/libur` dan skor.
- **Masa pembinaan** (`MasaPembinaan`): tabel 2 kolom `angkatan` (PK) + `tanggal_selesai` (nullable date).

### 6.4 `PembinaanService::luluskanAngkatanSelesai()`

```php
$cohorts = MasaPembinaan::whereNotNull('tanggal_selesai')->where('tanggal_selesai','<=',today())->pluck('angkatan');
Anak::whereIn('angkatan',$cohorts)->where('status', Anak::STATUS_AKTIF)->update(['status' => Anak::STATUS_LULUS]);
```

- Mengembalikan jumlah baris yang diubah.
- **Pemanggil tunggal:** `ApiController::__invoke()` baris 44 — dijalankan pada **setiap** request `/absensi/api`
  (meniru `semarak_init_schema()` native). Efek samping tersembunyi: status anak bisa berubah hanya karena admin membuka dashboard.
- **Tidak** membuat/menyentuh `users` sama sekali → tidak ada akun Klien otomatis saat lulus (F-07).
- Mengubah `status` anak secara manual di `AnakResource` juga tidak berefek ke `users`.

---

## 7. Skema database saat ini

### 7.1 Koneksi (fakta hasil pemeriksaan)

| Koneksi | Driver | Database | Dipakai untuk |
|---------|--------|----------|---------------|
| default (`DB_CONNECTION`) | `sqlite` | `database/database.sqlite` | `users`, `user_profiles`, master data, dll |
| `semarak` | `mysql` | `semarak_oracle` | `anak`, `kegiatan`, `evaluasi`, `jadwal_kegiatan`, `masa_pembinaan`, `catatan_rapor` |

Saat tes (`phpunit.xml`) **keduanya** diarahkan ke SQLite `:memory:` (`SEMARAK_DB_CONNECTION=sqlite`,
`SEMARAK_DB_DATABASE=:memory:`) → tidak pernah menyentuh DB dev/produksi.

### 7.2 Tabel `users` (11 kolom)

| Kolom | Tipe | Nullable | Index/Unique |
|-------|------|----------|--------------|
| `id` | integer | no | PK, autoincrement |
| `name` | varchar | no | — |
| `email` | varchar | **no** | `users_email_unique` (unique) |
| `email_verified_at` | datetime | yes | — |
| `password` | varchar | **no** | — |
| `user_type` | varchar | yes (default `'client'`) | — |
| `remember_token` | varchar | yes | — |
| `created_at` | datetime | yes | — |
| `updated_at` | datetime | yes | — |
| `status` | varchar | yes | `users_status_index` |
| `created_by` | integer | yes | `users_created_by_index` |

Migrasi terkait: `0001_01_01_000000_create_users_table`, `2024_08_17_030121_table_users_add_status`,
`2026_11_10_090000_add_created_by_to_users_table`.
**Belum ada** kolom `anak_id`, `graduated_at`, dan `email`/`password` masih NOT NULL (lihat §12).

### 7.3 Tabel `user_profiles` (22 kolom)

`id` (PK), `user_id` (**FK → users.id, on delete CASCADE**), `description`, `birth_place`, `birth_date`, `gender`,
`ktp_address`, `ktp_city_id`, `domisili_address`, `domisili_city_id`, `education_degree_id`, `body_weight`, `body_height`,
`religion_id`, `ethnic_group_id`, `profile_picture_path`, `skck_status`, `surat_kesehatan_status`, `hire_status`, `status`,
`created_at`, `updated_at` — semua nullable kecuali `id`,`user_id`.
FK: `ktp_city_id`/`domisili_city_id` → `master_cities`, `education_degree_id` → `master_education_degrees`,
`religion_id` → `master_religions`, `ethnic_group_id` → `master_ethnic_groups` (semua `on delete set null`).

### 7.4 Tabel `semarak.anak` (8 kolom, tanpa timestamps)

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | varchar(20) | PK, format `AN-001` |
| `nama` | varchar(150) | required (validasi aplikasi) |
| `jenis_kelamin` | varchar(20) | `Laki-laki`/`Perempuan` (teks bebas) |
| `asal_daerah` | varchar(100) | |
| `agama` | varchar(50) | |
| `umur` | int | > 0 |
| `angkatan` | varchar(50) | dikaitkan ke `masa_pembinaan.angkatan` |
| `status` | varchar(30) | default `Aktif`; nilai lain `Lulus` |

`semarak.masa_pembinaan`: `angkatan` varchar(50) PK, `tanggal_selesai` date nullable.
`AbsensiSchemaTest` mengunci parity kolom model `Anak` vs migrasi ini — **jangan ubah**.

### 7.5 Data aktual (read-only, 12 Nov 2026)

- `users`: **4** baris → 2 `admin`, 1 `admin_absensi`, 1 `client` (`klien@pendekar1721.test`, status `published`, `created_by` NULL).
- `semarak.anak`: **7** baris, semua `Aktif`; angkatan `23` (1), `26` (1), `Angkatan II` (5).
- Rincian lengkap ada di §12.

---

## 8. Baseline tes (SEBELUM perubahan)

Perintah: `php artisan test` · Hasil: **76 passed, 1 failed, 292 assertions, 20.49s**.

### 8.1 Satu kegagalan (pre-existing, bukan akibat refactor)

`Tests\Feature\Absensi\AbsensiApiParityTest::test_seluruh_skenario_oracle_identik`

- Oracle (dump native) menyimpan `tanggal_catat`/`periode_mulai` = **2026-11-11**, sedangkan aplikasi memakai
  `now()` = **2026-11-12** (tanggal sistem saat tes dijalankan).
- Selisih hanya pada tanggal default 2 skenario (`get-report-default`, `get-report-anak-tidak-ada`);
  struktur respons, skor, dan detail identik.
- **Kesimpulan:** gagal karena *date drift* pada fixture, bukan regresi kode. Dibiarkan apa adanya;
  tidak terkait refactor panel Klien.

### 8.2 Daftar file tes & cakupannya (77 tes)

| File | Jml | Cakupan |
|------|-----|---------|
| `tests/Feature/ExampleTest.php` | 6 | halaman publik & nama aplikasi di login |
| `tests/Unit/ExampleTest.php` | 1 | sanity unit |
| `tests/Feature/Absensi/AbsensiApiParityTest.php` | 3 | parity seluruh skenario oracle + guard tamu/non-admin |
| `tests/Feature/Absensi/AbsensiFilamentTest.php` | 2 | `AnakResource`/`KegiatanResource` untuk admin; klien ditolak |
| `tests/Feature/Absensi/AbsensiPagesTest.php` | 6 | semua halaman absensi, kop rapor, navigasi silang, guard |
| `tests/Feature/Absensi/AbsensiSchemaTest.php` | 7 | parity skema `semarak`, enum, unique, cascade, timezone |
| `tests/Feature/Auth/UnifiedLoginTest.php` | 16 | login terpadu, redirect per role, akun arsip, logout panel |
| `tests/Feature/Security/ClientPesertaScopingTest.php` | 4 | **scoping `created_by` panel Klien (akan diganti)** |
| `tests/Feature/Security/ImportHardeningTest.php` | 5 | batas baris, tanpa password default, verifikasi hash, pemilik data |
| `tests/Feature/Security/LoginSecurityTest.php` | 8 | no-store, CSP, case-insensitive email, pesan seragam, rate limit, session fixation, rehash |
| `tests/Feature/Security/MassAssignmentTest.php` | 3 | `unguard` mati, atribut asing, `user_id` profil |
| `tests/Feature/Security/PanelAuthorizationTest.php` | 4 | admin↔klien tidak boleh lintas panel |
| `tests/Feature/Security/ProfileOwnershipTest.php` | 2 | profil lewat relasi, klien tanpa profil tetap bisa buka |
| `tests/Feature/Security/PublicProfileVisibilityTest.php` | 5 | hanya `published` yang tampil publik |
| `tests/Feature/Security/SecurityHeadersTest.php` | 3 | header keamanan, tanpa versi PHP |
| `tests/Feature/Security/SelfHostedAssetsTest.php` | 2 | font self-hosted |

### 8.3 Perintah verifikasi yang dipakai

```powershell
php artisan test
vendor/bin/pint --dirty
vendor/bin/phpstan analyse   # opsional, bila tersedia
```

---

## 9. Ringkasan fungsi file yang akan dihapus/diubah

Bukan sekadar nama — ini isi & perannya, agar bisa direkonstruksi dari dokumen ini walau file sudah hilang.

### 9.1 `app/Filament/Client/Resources/PesertaResource.php` (AKAN DIHAPUS)

- `$model = User`, label "Peserta"/"Data Peserta", ikon `heroicon-o-users`, `navigationSort = 2`.
- `getEloquentQuery()`: `where('user_type','client')` + scoping `where('created_by', auth()->id())` untuk user client.
- `form()` 3 section (Informasi Akun / Profil Lengkap via relasi `userProfile` / Keterampilan & Penempatan) —
  field & aturan persis seperti §3.3.
- `table()` 6 kolom (foto, nama, email, gender badge, pendidikan, hire_status) + `EditAction`.
- `getPages()`: `index` = `ListPesertas`, `create` = `CreatePeserta`, `edit` = `EditPeserta`.
- **Peran bisnis saat ini:** klien dapat mendaftarkan/mengelola "peserta" (yang sebenarnya user `client`) dari panelnya sendiri.

### 9.2 `.../PesertaResource/Pages/CreatePeserta.php` (AKAN DIHAPUS)

- `mutateFormDataBeforeCreate()`: paksa `user_type='client'`, `status='published'`, `created_by=auth()->id()`.
- Hanya 3 baris logika; **satu-satunya tempat** status Published dipaksakan untuk peserta buatan klien.

### 9.3 `.../PesertaResource/Pages/EditPeserta.php` (AKAN DIHAPUS)

- `EditRecord` polos; `getHeaderActions()` kosong (tidak ada Delete).
- Tidak ada `mutateFormDataBeforeSave` → proteksi edit hanya dari `getEloquentQuery()`.

### 9.4 `.../PesertaResource/Pages/ListPesertas.php` (AKAN DIHAPUS)

- `ListRecords` + header action `CreateAction` berlabel "Tambah Peserta Manual" (ikon `heroicon-o-plus`).
- Tidak ada bulk delete.

### 9.5 `app/Filament/Client/Pages/ImportPeserta.php` (AKAN DIHAPUS)

- Page Livewire (`HasForms`, `WithFileUploads`), slug `import-peserta`, judul "Import Data Peserta", sort 3.
- `canAccess()`: `user_type === 'client'`.
- `getFormSchema()`: upload file (private disk `local`), upload banyak foto (`preserveFilenames`), kata sandi awal.
- `downloadExcelTemplate()` / `downloadWordTemplate()`: membuat template 14 kolom di `storage/app` lalu
  `response()->download(...)->deleteFileAfterSend(true)`.
- `processPreview()`: pastikan file ada di disk `local` → `ImportService::parseAndValidate()` → isi `$previewData`.
- `confirmImport()`: guard error/valid, verifikasi kata sandi awal, `ImportService::importData()`,
  notifikasi "{$totalValid} data berhasil ditambahkan. {$totalDuplikat} data dilewati karena duplikat.", reset form.

### 9.6 `resources/views/filament/client/pages/import-peserta.blade.php` (AKAN DIHAPUS)

- 178 baris. Tombol unduh template Excel & Word; `<form wire:submit.prevent="processPreview">` + tombol "Preview Data".
- Setelah preview: 4 kartu statistik (Total / Valid / Error / Duplikat).
- Kartu "Daftar Error Validasi" (tabel baris, kolom, nilai, pesan; Import dinonaktifkan),
  kartu "Data Duplikat (Akan Dilewati)" (berdasarkan Email),
  kartu "Preview Data (Siap Import)" (maks 50 baris + catatan "Menampilkan 50 data pertama dari N"),
  lalu form konfirmasi import.
- Semua teks & struktur ini hilang saat file dihapus; salinan tersedia di `docs/backup-pra-refactor/`.

### 9.7 Fungsi yang **hilang** dari panel Klien dan penggantinya

| Fungsi lama | Nasib | Pengganti |
|-------------|-------|-----------|
| Klien menambah peserta manual | dihapus | Admin via `UserResource` / aksi kelulusan |
| Klien mengedit peserta | dihapus | Klien hanya mengedit profil sendiri |
| Klien import Excel/Word | dihapus | Admin `ImportData` (mode binaan, Draft) |
| Klien melihat daftar peserta | dihapus | Angka alumni (D-02), daftar opsional read-only |
| Statistik "Tersedia (Siap Kerja)" lintas user | diganti | "Kelengkapan Profil" milik sendiri |
| Aksi Cepat (Tambah/Import) | dihapus | Tidak ada |

---

## 10. Rencana perubahan Fase 0 & Fase 1 (yang akan dieksekusi setelah dokumen ini)

### Fase 0 — Hardening (tanpa fitur baru)

| Kode | File | Perubahan |
|------|------|-----------|
| F-02 | `app/Models/User.php` | `canAccessPanel()` menolak `UserStatus::Draft` dan `UserStatus::Archived` untuk **semua** panel; `status === null` tetap diizinkan |
| F-03 | `app/Http/Controllers/Auth/LoginController.php` | cabang baru **sebelum** `Archived`: `Draft` → "Akun Anda belum diaktifkan. Silakan hubungi administrator Pendekar 1721." |
| F-01 | `Dashboard.php` + `dashboard.blade.php` | `getPesertaTerbaru()` dihapus; blok tabel peserta dihapus |
| tes | `tests/Feature/Security/AccountStatusLoginTest.php` (baru) | Draft ditolak login; Published diterima; Archived tetap ditolak; user Draft tidak bisa buka panel |
| tes | `tests/Feature/Security/ClientDashboardPrivacyTest.php` (baru) | dashboard klien tidak memuat nama/email user client lain |

### Fase 1 — Bersihkan panel Klien

1. Hapus 6 file (§9.1–9.6).
2. `Dashboard` baru: kartu **Jumlah Alumni**, **Pelatihan Saya**, **Kelengkapan Profil**; tanpa daftar orang lain, tanpa Aksi Cepat.
3. `TrainingResource`: `canCreate()/canEdit()/canDelete() = false` eksplisit.
4. `AlumniResource` (baru, read-only) hanya muncul bila `config('pendekar.alumni.directory_enabled') === true`;
   kolom tanpa email/telepon/alamat.
5. `UserProfileEditPage`: tetap `auth()->user()->userProfile` (sudah aman) — tambah guard agar tidak pernah memakai ID lain.
6. `config/pendekar.php`: tambah `'alumni' => ['directory_enabled' => env('PENDEKAR_ALUMNI_DIRECTORY', false)]`.
7. Tes: hapus `ClientPesertaScopingTest`, buat `ClientPanelMenuTest`:
   `/klien` 200; `/klien/pesertas` 404; `/klien/import-peserta` 404; `/klien/trainings` 200;
   `/klien/alumni` 404 saat config `false` (dan 200 saat `true` bila resource dibuat).

### Batasan keras selama Fase 0–1

- **Tidak** menyentuh `routes/absensi.php`, `ApiController`, `EnsureAbsensiAdmin`, `database/migrations/semarak/*`, `AbsensiSchemaTest`.
- **Tidak** menghapus data di database. Semua perubahan skema lewat migrasi yang bisa di-rollback (Fase 2+).
- Bila ada hal bertentangan dengan rencana → berhenti & tanya, jangan menebak.

---

## 11. Catatan Fase Lanjutan (WAJIB — belum dikerjakan sekarang)

> **STATUS (12 Nov 2026): butir 1–4 SUDAH diimplementasikan pada Fase 2.**
> - Migrasi: `2026_11_12_090000_add_anak_link_to_users_table.php` (`anak_id` unique + `graduated_at`),
>   `2026_11_12_090100_make_users_email_and_password_nullable.php`.
> - Service: `App\Services\GraduationService` (kompensasi + password sekali tampil),
>   `App\Services\ImportBinaanService` (jembatan `anak` → akun `Draft`).
> - Guard login password NULL: `LoginController::kredensialValid()`.
> - Tes: `tests/Feature/Graduation/GraduationOnboardingTest.php` (8 tes) +
>   1 tes baru di `AccountStatusLoginTest`.
> - **Belum dikerjakan:** parameter `mode` pada `ImportService` (dipakai `ImportData` admin) dan UI aksi
>   kelulusan — keduanya bagian Fase 3. Backfill `graduated_at` menunggu audit produksi.

Empat syarat yang **harus** dipenuhi saat Fase 2–3 (GraduationService/ImportBinaan) dikerjakan, plus tesnya
di `tests/Feature/Graduation/GraduationOnboardingTest.php`.

1. **Validasi email saat "Luluskan" harus `ignore()` user yang sudah terhubung.**
   `->unique(User::class, 'email', ignore: $anak->user?->id)` atau cek manual `where('anak_id', $anak->id)`.
   Tujuan: email hasil import (mis. `budi@…` yang sudah terdaftar sebagai Draft milik anak itu) tidak ditolak
   sebagai "sudah dipakai".

2. **Kompensasi kegagalan mengembalikan status SEBELUMNYA, bukan selalu Draft.**
   Simpan `$statusSebelum = $user->status` sebelum diubah; pada kegagalan tulis `semarak`/profil:
   - user lama → kembalikan `$statusSebelum` (bisa `published`, `null`, …);
   - user baru dibuat → hapus user+profil, atau tandai `Draft` bila penghapusan berisiko.
   Catat alasan kompensasi di `Log::error('graduation.rollback', [...])`.

3. **Password acak ditampilkan ke admin tepat satu kali.**
   Kembalikan plaintext dari `GraduationService::luluskan()` (mis. `['password' => ...]`) dan tampilkan lewat
   modal/notifikasi persisten Filament **setelah** aksi sukses. Jangan simpan plaintext di DB/log/audit trail.

4. **`users.password` nullable** untuk binaan Draft → migrasi `make_users_password_nullable` di Fase 2
   (sekarang NOT NULL, lihat §12). Login/Hash harus aman terhadap password `NULL`:
   user tanpa password **tidak boleh bisa login** (guard sebelum `Hash::check`, mis. `if (blank($user->password)) return false;`
   dan pesan gagal yang sama seperti kredensial salah).

Tambahan yang tetap berlaku: `anak_id` **nullable + unique** dan `graduated_at` (datetime, nullable) di `users`;
`ImportService` diberi parameter `mode` (`binaan` → `Draft`, `klien` → `Published`) + dedup nama/asal/angkatan;
`generateId()` harus aman terhadap pemanggilan bersamaan.

---

## 12. Hasil pemeriksaan data (langkah 4 & 5)

Dijalankan **read-only** pada database aplikasi (skrip: `docs/audit/audit-users.php`) tanggal 12 Nov 2026.

### 12.1 (4) Skema `users.password`

- Tipe `varchar`, **NOT NULL**, tanpa default → **belum nullable**.
- Data aktual: `password IS NULL` = 0, `password = ''` = 0.
- Kesimpulan: migrasi `make_users_password_nullable` **perlu dibuat di Fase 2** (belum dibuat sekarang),
  dan login harus menolak user berpassword `NULL` (syarat §11.4).

### 12.2 (5) Apakah semua user `client` memang alumni?

**Penting: angka "500+" TIDAK terkonfirmasi di environment ini.** Database default ternyata
**SQLite** (`database/database.sqlite`) yang hanya berisi **4 user** — kemungkinan besar data 500+ ada di
database produksi/staging yang tidak dapat diakses dari sini.

Angka aktual (dev):

| Kelompok | Jumlah |
|----------|--------|
| Total `users` | 4 |
| `admin` + `published` | 2 |
| `admin_absensi` + `published` | 1 |
| `client` + `published` | 1 |
| `client` + `draft` / `archived` | 0 / 0 |
| `client` dengan `created_by` terisi | 0 (satu-satunya client: `created_by = NULL`) |
| `client` per tahun `created_at` | 2026 → 1 |
| `client` tanpa `userProfile` | 0 |
| `client` tanpa pelatihan (`user_trainings`) | 1 |
| `client` tanpa foto profil | 0 |
| Email duplikat (case-insensitive) | 0 |

Satu user client: `#6 Klien Pendekar <klien@pendekar1721.test>`, status `published`, `created_by` NULL,
dibuat 2026-11-11 10:21:48 (akun demo `RoleAccountSeeder`).

`semarak.anak`: 7 baris, semuanya `status='Aktif'`, angkatan `23`, `26`, `Angkatan II`.
`users.anak_id` dan `users.graduated_at` **belum ada**.

**Kesimpulan & batasan:**
1. Tidak ada data yang bisa dipakai untuk memvalidasi definisi "alumni" di lokal; tidak ada indikasi kebocoran
   massal yang bisa diukur dari sini.
2. Definisi alumni untuk `scopeAlumni()` **sudah final** (dikonfirmasi pemilik produk, 12 Nov 2026):
   `user_type = 'client'` AND `status = 'published'` AND `graduated_at IS NOT NULL`.
   Klausa `created_by IS NULL` **DITOLAK** sebagai penanda alumni (klien #6 juga `created_by` NULL
   padahal baru dibuat). Implementasi: `App\Models\User::scopeAlumni()`.
3. Angka kartu "Jumlah Alumni" di dashboard **masih definisi sementara** (`user_type = 'client'`) dan
   diberi label "Angka sementara (belum final)" di UI, karena `graduated_at` belum diisi.
   Setelah backfill selesai: ganti ke `User::query()->alumni()->count()` dan hapus penanda
   `total_alumni_belum_final`.
4. Untuk memverifikasi klaim 500+ di produksi, jalankan `docs/audit/audit-users.php` pada server
   produksi (read-only); petunjuk lengkap: `docs/AUDIT-PRODUKSI-USERS.md`.

---

## 13. Catatan hasil eksekusi Fase 0–1 (diisi setelah perubahan)

### 13.1 Integritas backup

- Salinan dibuat dengan `Copy-Item`, lalu **diregenerasi byte-exact** dari git index memakai
  `git checkout-index --prefix=docs/backup-pra-refactor/ -f -- <paths>` (15 file, semuanya versi HEAD `37bd85f7`).
- Ditambahkan `pint.json` (`preset: laravel`, `exclude: docs/backup-pra-refactor`) agar `vendor/bin/pint`
  **tidak pernah** merapikan isi backup di masa depan.
- Tidak ada commit/tag git baru; working tree tetap memuat WIP absensi yang sudah ada sebelumnya.

### 13.2 Perubahan yang benar-benar dilakukan

| File | Aksi |
|------|------|
| `app/Models/User.php` | `canAccessPanel()` menolak `Draft` & `Archived` (F-02) |
| `app/Http/Controllers/Auth/LoginController.php` | cabang `Draft` + pesan aktivasi (F-03) |
| `app/Filament/Client/Pages/Dashboard.php` | `getPesertaTerbaru()` dihapus; kartu Jumlah Alumni / Pelatihan Saya / Kelengkapan Profil (F-01) |
| `resources/views/filament/client/pages/dashboard.blade.php` | ditulis ulang; tabel peserta & Aksi Cepat dihapus |
| `app/Filament/Client/Resources/PesertaResource.php` + 3 Pages | **dihapus** |
| `app/Filament/Client/Pages/ImportPeserta.php` + blade | **dihapus** |
| `app/Filament/Client/Resources/TrainingResource.php` | `canCreate/canEdit/canDelete/canDeleteAny = false` |
| `app/Filament/Client/Resources/AlumniResource.php` + `Pages/ListAlumnis.php` | **baru**, read-only, slug `alumni`, 404 bila config mati |
| `app/Filament/Client/Pages/UserProfileEditPage.php` | `canAccess()` + guard kepemilikan profil `auth()->id()` |
| `config/pendekar.php` | `alumni.directory_enabled` (default `false`) |
| `tests/Feature/Security/ClientPesertaScopingTest.php` | **dihapus** |
| `tests/Feature/Security/AccountStatusLoginTest.php` | **baru** (8 tes) |
| `tests/Feature/Security/ClientDashboardPrivacyTest.php` | **baru** (3 tes) |
| `tests/Feature/Security/ClientPanelMenuTest.php` | **baru** (8 tes) |
| `pint.json` | **baru** (exclude folder backup) |

Route panel Klien setelah refactor: `klien`, `klien/trainings`, `klien/alumni` (404 saat config mati),
`klien/user-profile-edit-page`, `klien/logout`.

### 13.3 Hasil tes & pint

- `php artisan test`: **91 passed, 1 failed, 330 assertions**.
  Satu-satunya kegagalan tetap `AbsensiApiParityTest` karena `tanggal_catat` oracle `2026-11-11`
  vs aplikasi `2026-11-12` (date drift, sudah ada sebelum refactor).
- `vendor/bin/pint --dirty`: **PASS 17 files** (hanya file aplikasi, folder backup tidak disentuh).
- Efek samping yang perlu diketahui: pada run pint **pertama** (sebelum `pint.json` dibuat), Pint juga
  menyentuh beberapa file WIP yang sudah kotor sebelum refactor
  (`app/Http/Middleware/SecurityHeaders.php`, `app/Modules/Absensi/Http/Controllers/PageController.php`,
  `tests/Feature/Absensi/AbsensiPagesTest.php`, `tests/Feature/Security/LoginSecurityTest.php`,
  `tests/Feature/Security/SelfHostedAssetsTest.php`).
- **KOREKSI (12 Nov 2026):** klaim "perubahannya hanya gaya (line ending/spasi)" di atas **tidak akurat**.
  `git diff -U0` membuktikan kelima file itu memang membawa **perubahan logika WIP yang sudah ada sebelumnya**:
  `font-src 'self'` pada CSP, `anakList` di `PageController`, penyesuaian label "Daftar Anak"/"Cetak Dokumen
  Raport", serta tes `SelfHostedAssetsTest` untuk font Inter yang di-self-host. Sumbangan Pint di file-file itu
  hanyalah normalisasi line ending yang **tidak muncul** di diff git.
  Konsekuensinya: **tidak ada perubahan Pint yang bisa dipisahkan menjadi commit tersendiri**. Kelima file
  sengaja **tidak di-commit** karena fitur self-host font masih bergantung pada berkas yang belum ter-track
  (`public/fonts/**`, `resources/sass/_fonts.scss`, `resources/views/layouts/base.blade.php`,
  `public/build/**`); memisahkannya justru membuat `SelfHostedAssetsTest` gagal.












---

## 14. Catatan hasil eksekusi Fase 2 (skema & service)

Tanggal: 12 Nov 2026. Lingkup **sengaja dibatasi**: skema database + service + tes.
Backfill `graduated_at` dan UI aksi kelulusan **belum** dikerjakan.

### 14.1 Migrasi

| File | Perubahan |
|---|---|
| `2026_11_12_090000_add_anak_link_to_users_table.php` | `users.anak_id` (string 20, nullable, **unique**) + `users.graduated_at` (timestamp nullable, index). Tanpa foreign key karena tabel `anak` ada di koneksi `semarak` |
| `2026_11_12_090100_make_users_email_and_password_nullable.php` | `email` & `password` nullable; `down()` mengisi NULL dengan `binaan-{id}@invalid.local` / `''` sebelum mengembalikan NOT NULL |

Catatan: `anak_id` unique di SQLite/MySQL tetap mengizinkan banyak nilai NULL, sehingga akun lama
tanpa tautan tidak saling bentrok.

### 14.2 Kode

| File | Isi |
|---|---|
| `app/Models/User.php` | `anak_id` + `graduated_at` (fillable/cast), `scopeAlumni()` (definisi final), `isAlumni()`, relasi `anak()` |
| `app/Http/Controllers/Auth/LoginController.php` | `kredensialValid()` menolak `password` NULL/kosong, tetap menjalankan hash dummy (timing-safe) |
| `app/Services/ImportBinaanService.php` | jembatan `anak` → akun `Draft` (`email`/`password` NULL), idempoten, dedup nama kembar, `importSemua()` untuk batch |
| `app/Services/GraduationService.php` | `luluskan()`: email unik dengan *ignore* akun tertaut, password acak sekali tampil, kompensasi atribut akun + status anak, `Log::error('graduation.rollback')` |
| `app/Filament/Client/Resources/AlumniResource.php` | `getEloquentQuery()` memakai `->alumni()` |
| `app/Filament/Client/Pages/Dashboard.php` + `dashboard.blade.php` | penanda `total_alumni_belum_final` + label "Angka sementara (belum final)" |
| `docs/audit/audit-users.php` | skrip audit produksi read-only (menggantikan `storage/app/audit-users.php`), opsi `AUDIT_MASK_PII=1` |
| `docs/AUDIT-PRODUKSI-USERS.md` | petunjuk audit produksi (baru) |
| `docs/SECURITY-AUDIT-L13.md` | catatan "usang" di bagian atas |

### 14.3 Tes

- `tests/Feature/Graduation/GraduationOnboardingTest.php` (**baru, 8 tes**): definisi alumni, impor binaan
  (Draft/tanpa email/tanpa password), idempotensi + nama kembar, kelulusan akun tertaut, pembuatan akun baru,
  email bentrok, email tidak valid, kompensasi kegagalan.
- `tests/Feature/Security/AccountStatusLoginTest.php`: **+1 tes** "akun published tanpa kata sandi tidak bisa login".
- `php artisan test`: **101 passed (390 assertions)** — baseline hijau; `AbsensiApiParityTest` ikut hijau
  setelah date drift dinormalkan (§14.4).

### 14.4 Perbaikan `AbsensiApiParityTest` (date drift)

- Penyebab: modul absensi memakai `date('Y-m-d')` (bukan `now()`) demi parity dengan SEMARAK, sehingga
  `Carbon::setTestNow()` **tidak** membekukan periode default → oracle `2026-11-11` vs aplikasi "hari ini".
- Perbaikan (tes saja, kode modul tidak disentuh): `normalkanTanggal()` mengganti tanggal hari ini dengan
  `TANGGAL_FIXTURE = '2026-11-11'` sebelum body dibandingkan. Fixture oracle **tidak** diubah.

### 14.5 Yang BELUM dikerjakan (menunggu keputusan)

1. **Backfill `graduated_at`** — menunggu hasil audit produksi (`docs/AUDIT-PRODUKSI-USERS.md`).
2. **UI aksi "Luluskan & Aktifkan Akun Klien"** di `AnakResource` + tampilan password sekali + Reset Password
   di `UserResource` (Fase 3).
3. **Parameter `mode` pada `ImportService`** (`binaan` → Draft) + dedup nama/asal/angkatan untuk `ImportData`
   admin (Fase 3).
4. Kartu "Jumlah Alumni" tetap angka sementara sampai butir 1 selesai.

### 14.6 Commit terpisah

| Commit | Isi |
|---|---|
| 1 | `test(absensi)`: normalisasi tanggal fixture pada oracle parity — `43626745` |
| 2 | Fase 0–1–2: panel Klien read-only + hardening status akun + onboarding alumni + dokumen — `74d6888b` |

Catatan: Fase 0/1/2 berada dalam **satu commit** karena `app/Models/User.php` dan beberapa berkas tes memuat
perubahan dari lebih dari satu fase sehingga tidak bisa dipisah per-fase tanpa memecah satu file.
Permintaan "Pint pada 5 file WIP dijadikan commit tersendiri" **tidak dapat dijalankan**: kelima file itu
tidak memuat perubahan gaya hasil Pint yang bisa dipisahkan (lihat koreksi §13.3), sehingga ditinggalkan
sebagai WIP milik Anda.

File WIP absensi & self-host font **tidak** di-commit.

