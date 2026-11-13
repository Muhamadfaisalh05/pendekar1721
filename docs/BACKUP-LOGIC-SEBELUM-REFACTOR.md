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
3. Angka kartu "Jumlah Alumni" di dashboard **sejak Fase 4** memakai definisi final begitu ada satu
   `graduated_at`; selama belum ada, kartu memakai fallback definisi lama (`client` + `published`) dengan
   label "Angka sementara (belum final)" dan beralih otomatis setelah backfill (lihat §16.2).
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
4. Kartu "Jumlah Alumni" beralih ke definisi final otomatis begitu backfill mengisi `graduated_at` pertama
   (fallback berlabel "Angka sementara (belum final)" tetap ada sampai saat itu — lihat §16.2).

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


---

## 15. Catatan hasil eksekusi Fase 3 (12 Nov 2026)

Fase 3 menggantikan butir 2 & 3 pada §14.5. Backfill `graduated_at` (§14.5 butir 1) **tetap ditunda**
sampai hasil audit produksi diterima.

### 15.1 Yang dikerjakan

| Kebutuhan | Implementasi |
|---|---|
| Aksi "Luluskan & Aktifkan Akun Klien" | `AnakResource` (tabel `anak`, panel admin): aksi `luluskan` dengan form email + tanggal lulus (opsional, default hari ini), memanggil `GraduationService::luluskan()`. Terlihat hanya untuk `admin` dan anak yang belum `Lulus`. |
| Kata sandi tampil **satu kali** | `App\Filament\Actions\TampilkanKataSandiSekali` (nama aksi `kataSandiSekali`), dipasang lewat `replaceMountedTableAction(..., ['password' => ...])` **setelah** aksi sukses. Plaintext hanya ada di argumen aksi (memori request) — tidak di DB, tidak di log. View: `resources/views/filament/actions/kata-sandi-sekali.blade.php`. |
| Reset Password di `UserResource` | Aksi `resetPassword` (konfirmasi) → `UserAccountService::resetPassword()` (acak 24 karakter) + modal kata sandi sekali tampil yang sama. |
| Guard hapus akun | `UserAccountService::alasanTidakBolehDihapus()`: tidak boleh hapus akun sendiri, tidak boleh hapus administrator terakhir (admin `Archived` tidak dihitung sebagai cadangan), hanya `admin` yang boleh. Tombol hapus **disembunyikan** untuk dua kasus terlarang; service tetap menolak bila dipanggil dari jalur lain. |
| Kolom "Akun Klien" di daftar anak | `TextColumn::make('akun_klien')` (badge: Belum ada / Draft / Aktif / Diarsipkan) + deskripsi email, membaca relasi baru `Anak::akunKlien()`; eager load satu query per halaman (`modifyQueryUsing(...->with('akunKlien'))`). |
| Mode "Remaja Binaan" di Import Data | `ImportService::MODE_BINAAN` (`Draft`, `password = NULL`) dan `MODE_KLIEN` (`Published` + kata sandi). Form `ImportData` memakai `Radio` mode dengan **default `binaan`** (`mount()` mengisi ulang `mode` setiap kali form di-reset). |
| Penautan akun binaan **opt-in** (revisi review) | Tidak ada penautan otomatis berdasarkan nama. `Checkbox::make('tautkan_anak')` (label "Tautkan ke data anak yang namanya sama", default **mati**, hanya tampil untuk mode binaan) menentukan apakah `users.anak_id` diisi. Rencana tautan dihitung `ImportService::petaTautanAnak()` dan ditampilkan di langkah Preview **sebelum** import; `importData()` memakai peta yang sama sehingga yang ditautkan = yang dilihat operator. Nama kembar dan nama yang muncul lebih dari sekali di file tetap **tidak pernah** ditautkan. |
| Laporan "Kemungkinan Duplikat" (revisi review) | `ImportService::deteksiKemungkinanDuplikat()` membandingkan **nama + asal daerah + angkatan** dari tiga sumber: (1) baris lain di file yang sama, (2) data `semarak.anak`, (3) akun klien yang sudah ada dengan nama sama. Hasilnya ditampilkan di Preview sebagai peringatan (kolom Baris, Nama, Email, Sumber, Keyakinan, Keterangan) — baris **tidak ditolak** dan tetap diimpor. Kolom opsional `Asal Daerah` & `Angkatan` ditambahkan ke template Excel/Word; bila tidak diisi, perbandingan jatuh ke nama saja dengan keyakinan "Sedang". |


### 15.2 Keputusan desain & deviasi yang perlu direview

1. **Default mode service vs UI berbeda (disengaja).**
   `ImportService::importData()` default `MODE_KLIEN` agar pemanggil lama (dan tes lama) tidak berubah perilaku;
   form `ImportData` memakai `binaan` sebagai pilihan awal. Jadi "default Draft" hanya berlaku di UI admin.
2. **Penautan akun binaan ke `semarak.anak` bersifat OPT-IN (revisi review).**
   Semula penautan berjalan otomatis; atas keputusan pemilik produk, penautan kini hanya terjadi bila operator
   mencentang "Tautkan ke data anak yang namanya sama" (default **mati**). Modul memakai `petaTautanAnak()` yang
   sama untuk Preview dan import, jadi hasil tautan selalu sama dengan yang diperiksa operator. Aturan tetap ketat:
   hanya nama yang PERSIS sama (case-insensitive), nama itu harus unik di `anak`, tidak muncul lebih dari sekali di
   file, dan anak belum punya akun. Nama kembar **tidak pernah** ditautkan meski opsi dicentang.
   Kegagalan membaca koneksi `semarak` hanya menghasilkan `Log::warning('import.binaan.taut_gagal')` — impor tetap jalan.
3. **`DeleteBulkAction` di `UserResource` dihapus.** Guard "tidak hapus diri sendiri / admin terakhir" tidak bisa dijamin
   pada hapus massal, jadi penghapusan akun kini satu per satu. (Hapus massal anak binaan tidak diubah.)
4. **Aksi modal kata sandi memakai `hidden()` dinamis, bukan statis.** Filament menandai aksi yang `hidden()` sebagai
   `isDisabled()`, sehingga tidak bisa dipasang programatik. Karena itu visibilitasnya `blank($arguments['password'])`.
5. **`Anak::akunKlien()` dibangun manual** (`new HasOne(...)`), bukan `$this->hasOne(...)`: `Model::newRelatedInstance()`
   menyalin koneksi parent (`semarak`) ke model `User` sehingga query mencari tabel `users` di database SEMARAK.
6. **Bug lama ditemukan & diperbaiki di `ImportData`.** Form halaman ini memakai state path kosong (setiap field =
   properti komponen). `photos` dan `import_password` tidak punya properti publik sehingga `$this->form->getState()`
   melempar `No property found for validation: [photos]` — artinya Preview/Import lewat UI gagal sebelum perubahan ini.
   Ditambahkan `public $photos = []` dan `public $import_password`.
7. **Pint** juga merapikan `class_attributes_separation` pada beberapa berkas yang disentuh (`ImportData`, `ImportService`,
   `Anak`) — perubahan gaya, bukan logika.
8. **Batas "sekali tampil".** Plaintext kata sandi hanya hidup di argumen aksi Livewire (payload milik admin yang
   menjalankan aksi) dan tidak pernah ditulis ke DB/log/audit trail. Setelah modal ditutup (`unmountTableAction`),
   argumen dibuang sehingga kata sandi tidak bisa ditampilkan lagi; satu-satunya jalan adalah "Reset Kata Sandi".
9. **"Kemungkinan duplikat" adalah peringatan, bukan penolakan (revisi review).** Sesuai keputusan pemilik produk,
   baris yang namanya sama dengan data anak/akun lain (dan asal daerah + angkatan sama bila kolom di file diisi)
   tetap masuk daftar `valid` dan tetap diimpor; laporan hanya ditampilkan di Preview. Asal daerah/angkatan yang
   **berbeda** membuat baris tidak dilaporkan (dianggap orang lain). Kolom `Asal Daerah` dan `Angkatan` di template
   bersifat opsional dan tidak disimpan ke `users` — hanya dipakai untuk perbandingan & keterangan.

### 15.3 Tes

| Berkas | Isi |
|---|---|
| `tests/Feature/Graduation/GraduationOnboardingTest.php` (+4) | aksi panel mengaktifkan akun + kata sandi sekali tampil (syarat 3), email bentrok ditolak tanpa mengubah data & modal tetap terbuka (syarat 1), gating `bolehLuluskan()` (hanya admin, bukan anak Lulus), kolom "Akun Klien" + relasi lintas database |
| `tests/Feature/Graduation/ImportBinaanTest.php` (**baru, 16**) | mode binaan → Draft tanpa kata sandi, tautan hanya saat opsi dicentang (dan diabaikan untuk mode klien), tidak menautkan nama kembar/nama ganda di file/anak yang sudah berakun, Preview memisahkan "akan ditautkan" vs "tidak dapat ditautkan" beserta alasannya, deteksi kemungkinan duplikat (nama + asal + angkatan) dari file/data anak dan **tidak** menolak baris, akun Draft tidak bisa login (syarat 4), kata sandi awal diabaikan, kelulusan memakai akun hasil impor & mengabaikan email akun tertaut (syarat 1 & 3), mode klien tetap aktif & bisa login, ringkasan `importSemua()`, default mode `binaan` + opsi penautan mati di form Import Data, Preview menampilkan kartu penautan & kemungkinan duplikat |
| `tests/Feature/Security/UserAccountGuardTest.php` (**baru, 6**) | reset password mengganti hash & plaintext sekali, aksi panel reset menampilkan kata sandi sekali, tolak hapus akun sendiri, tolak hapus administrator terakhir (dengan admin kedua diizinkan), hanya admin boleh menghapus, tombol hapus tersembunyi untuk dua kasus terlarang |

Hasil: `php artisan test` → **127 passed (549 assertions)**; `pint --test` pada 10 berkas yang disentuh → **PASS**.

### 15.4 Belum dikerjakan

1. **Backfill `graduated_at`** — masih menunggu hasil audit produksi (`docs/AUDIT-PRODUKSI-USERS.md`).
   Status 12 Nov 2026 (keputusan Fase 4): migrasi backfill **ditunda**, sedangkan `Dashboard::statistikAlumni()`
   sudah memakai `User::query()->alumni()->count()` begitu ada minimal satu alumni. Selama belum ada,
   penanda `total_alumni_belum_final` + label "Angka sementara (belum final)" **tetap dipertahankan**
   sebagai fallback dan hilang otomatis setelah `graduated_at` pertama terisi (§16.2).
   Migrasi nanti tetap dibatasi daftar `id` yang disetujui, bukan `UPDATE` massal.
2. **Fase 4**: rapikan penuh `docs/SECURITY-AUDIT-L13.md` — **selesai** (§16).
3. Pekerjaan yang masih terbuka setelah Fase 3 (opsional, bukan bagian syarat): dedup **email** tetap satu-satunya
   penolakan otomatis; laporan duplikat nama/asal/angkatan hanya peringatan. Bila nanti ingin penolakan otomatis,
   itu keputusan produk baru.

### 15.5 Keputusan review pemilik produk atas Fase 3 (12 Nov 2026)

1. Fase 3 di-commit sebagai commit tersendiri, **tanpa** file WIP absensi & self-host font.
2. Default `ImportService::importData()` tetap `MODE_KLIEN`; default UI tetap `MODE_BINAAN`. Tidak diubah.
3. Penautan otomatis `anak_id` berdasarkan nama **dilepas** dan diganti checkbox opsional "Tautkan ke data anak yang
   namanya sama" (default mati) + daftar tautan di langkah Preview. Nama kembar tetap tidak boleh ditautkan.
4. Dedup **nama + asal daerah + angkatan** pada import binaan dikerjakan dan dilaporkan di Preview sebagai
   "kemungkinan duplikat" (bukan penolakan diam-diam).
5. `DeleteBulkAction` pada `UserResource` tetap dihapus, tidak dikembalikan.

---

## 16. Catatan hasil eksekusi Fase 4 (12 Nov 2026)

Lingkup Fase 4: **definisi alumni di dashboard (dengan fallback sementara) + perapian dokumen audit**.
Tidak ada perubahan skema dan tidak ada migrasi data pada fase ini.

### 16.1 Yang dikerjakan

| Berkas | Perubahan |
|---|---|
| `app/Filament/Client/Pages/Dashboard.php` | `total_alumni` → `statistikAlumni()`: memakai `User::query()->alumni()->count()` begitu ada minimal satu alumni; bila **belum ada** klien `published` ber-`graduated_at`, hitungan jatuh ke definisi lama (`client` + `published`) dan `total_alumni_belum_final = true`. Docblock menjelaskan definisi final, fallback, dan asal backfill |
| `resources/views/filament/client/pages/dashboard.blade.php` | label "Angka sementara (belum final)" ditampilkan **hanya** saat `total_alumni_belum_final` bernilai `true` (di bawah angka, warna `warning`) |
| `tests/Feature/Graduation/AlumniStatistikDashboardTest.php` | **baru (4 tes)**: (1) fallback = `client` + `published` + label tampil saat `graduated_at` kosong; (2) angka + label **otomatis beralih** ke definisi resmi setelah satu `graduated_at` diisi; (3) definisi resmi tanpa label (`Draft`, `Archived`, admin, dan klien tanpa `graduated_at` tidak dihitung); (4) angka 0 dan masih bertanda sementara bila belum ada klien `published` |
| `docs/SECURITY-AUDIT-L13.md` | **ditulis ulang penuh** (1.139 → 269 baris): status per temuan (SELESAI/TIDAK BERLAKU/SEBAGIAN/TERBUKA), peta kontrol → implementasi → tes, checklist `.env` + verifikasi pasca-deploy, risiko residual, dan catatan apa yang dihapus dari versi lama. Versi 10 Nov tetap dapat dibaca dari git (`git show HEAD:docs/SECURITY-AUDIT-L13.md` sebelum perapian ini di-commit) |
| `docs/AUDIT-PRODUKSI-USERS.md` | §6 diperbarui: dashboard sudah memakai definisi resmi + fallback sementara berlabel; backfill tetap menunggu daftar `id` |
| `docs/BACKUP-LOGIC-SEBELUM-REFACTOR.md` | §15.4 diperbarui + §16 ini |

### 16.2 Keputusan pemilik produk (12 Nov 2026)

1. **Migrasi backfill `graduated_at` ditunda.** Daftar `id` hasil audit produksi belum diterima; migrasi tetap
   akan dibuat berbasis daftar `id` yang disetujui (bukan `UPDATE` massal) setelah daftar itu ada.
2. **Switch definisi dashboard dijalankan sekarang, dengan fallback sementara.** Perilaku kartu "Jumlah Alumni":
   - Ada minimal satu `client` + `published` + `graduated_at` terisi → angka = `alumni()->count()`,
     label "Angka sementara (belum final)" **tidak** tampil.
   - Belum ada satu pun (backfill belum jalan) → angka = definisi lama (`client` + `published`),
     label "Angka sementara (belum final)" **tampil** sebagai pengingat bahwa angka belum final.
   - Peralihan terjadi otomatis pada request berikutnya setelah `graduated_at` pertama terisi
     (tidak perlu deploy/ubah kode).
3. **Fase 4 hanya menyentuh dokumen + dua berkas dashboard + satu tes** (tanpa skema/migrasi), sehingga tidak
   mengubah perilaku import maupun kelulusan.
4. **Sumber kebenaran tetap `User::scopeAlumni()`.** Fallback hanya jembatan data, bukan definisi bisnis baru:
   begitu backfill selesai, cabang fallback tidak pernah dieksekusi lagi (tetap dipertahankan sebagai jaring
   pengaman bila data `graduated_at` hilang di masa depan).

### 16.3 Verifikasi

- `php artisan test` → **131 passed (570 assertions)**.
- `vendor/bin/pint --test` pada berkas yang berubah → **PASS**.
- `php artisan view:cache` → sukses.

### 16.4 Commit

Fase 4 (dokumen + dashboard + fallback + tes) di-commit **terpisah** dari WIP absensi & self-host font.
File WIP tersebut tetap berada di working tree dan tidak ikut di-stage.


---

## 17. Insiden error 500 `/admin/anak` & migrasi skema dev (12 Nov 2026)

Lingkup: **pengaman skema** (agar aplikasi tetap terbuka walau migrasi Fase 2 tertunda) dan **eksekusi
migrasi Fase 2 di database DEV**. Tidak ada backfill `graduated_at` dan tidak ada perubahan data.

### 17.1 Penyebab error 500

- Di database dev, migrasi Fase 2 (`2026_11_12_090000_add_anak_link_to_users_table` dan
  `2026_11_12_090100_make_users_email_and_password_nullable`) masih **Pending**.
- Kode Fase 3 sudah memakai kolom `users.anak_id`: kolom tabel "Akun Klien", eager load `with('akunKlien')`,
  dan aksi "Luluskan & Aktifkan Akun Klien". Akibatnya request `/admin/anak` gagal di level SQL
  (`no such column: users.anak_id`) → HTTP 500, bukan halaman kosong atau pesan yang bisa dipahami operator.
- Akar masalahnya bukan data, melainkan **urutan rilis**: kode lebih dulu naik daripada migrasi.

### 17.2 Pengaman skema yang ditambahkan

| Berkas | Pengaman |
|---|---|
| `app/Support/SchemaGuard.php` | **baru**. Satu-satunya tempat mengecek `Schema::hasColumn('users', 'anak_id')` / `graduated_at`. Hasil disimpan di properti static (cache **per request**, bukan cache lintas request) sehingga setelah migrasi dijalankan request berikutnya langsung melihat kolom baru. Ada `reset()` untuk tes dan `bolehKirimPeringatanMigrasi()` (sekali per request) |
| `app/Support/Relations/RelasiHasOneKosong.php` | **baru**. `HasOne` yang selalu kosong (`whereRaw('1 = 0')`), dipakai saat kolom penghubung belum ada agar query tidak pernah menyebut kolom yang tidak ada |
| `app/Modules/Absensi/Models/Anak.php` | `akunKlien()` mengembalikan relasi kosong bila `users.anak_id` belum ada — aman dipanggil/di-eager-load, hasil `null`, tanpa exception |
| `app/Filament/Resources/AnakResource.php` | Kolom "Akun Klien" + `with('akunKlien')` hanya dipasang bila kolom ada. Aksi "Luluskan & Aktifkan Akun Klien" **dinonaktifkan** (tetap terlihat) dengan tooltip "Jalankan migrasi database terlebih dahulu" |
| `app/Filament/Resources/UserResource.php` | Kolom `anak_id` dan `graduated_at` hanya dipasang bila kolomnya ada; daftar user tetap terbuka |
| `app/Models/User.php` | `scopeAlumni()` mengembalikan hasil kosong (`1 = 0`) bila `graduated_at` belum ada — tidak error SQL |
| `app/Filament/Client/Pages/Dashboard.php` | `statistikAlumni()` memakai fallback `client` + `published` + `belum_final = true` (label "Angka sementara (belum final)") bila `graduated_at` belum ada |
| `app/Services/GraduationService.php` | `luluskan()` menolak lebih awal dengan `InvalidArgumentException` berbahasa Indonesia (`PESAN_MIGRASI_TERTUNDA`, menyebut `php artisan migrate`), bukan error SQL |
| `app/Services/ImportBinaanService.php` | `pastikanAkunBinaan()` menolak dengan pesan migrasi; `adaNamaKembar()` tidak lagi menyebut `anak_id` bila kolomnya belum ada |
| `app/Services/ImportService.php` | `petaTautanAnak()` melaporkan semua baris "tidak ditautkan" dengan alasan migrasi; `importData()` tidak mengirim `anak_id` ke `INSERT` (impor klien/binaan tetap jalan) |
| `app/Filament/Support/PeringatanMigrasi.php` | **baru**. Notifikasi peringatan "Ada migrasi database yang belum dijalankan. Hubungi administrator sistem." — dipanggil dari `ManageAnak`, `ListUsers`, dan `ImportData`, sekali per request |
| `tests/TestCase.php` | `SchemaGuard::reset()` di `setUp()` supaya cache static tidak bocor antar test |
| `tests/Feature/Graduation/PengamanSkemaFase2Test.php` | **baru (8 tes, 50 assertions)**. Skenario kolom BELUM ada (kolom dilepas di dalam transaksi tes, pulih otomatis): `/admin/anak` 200 tanpa kolom "Akun Klien", aksi Luluskan nonaktif + peringatan, service menolak dengan pesan jelas, `/klien` 200 + label sementara, `/admin/users` 200, impor tetap jalan tanpa tautan, login + `canAccessPanel` tetap berjalan. Plus skenario kolom SUDAH ada: kolom tampil, aksi aktif, tanpa peringatan |

Commit pengaman skema: **`fb65aa27`** (`fix(admin): pengaman skema bila migrasi Fase 2 belum dijalankan`),
16 berkas, 572 insertions / 35 deletions — tanpa file WIP absensi & self-host font.

### 17.3 Backup sebelum migrasi dev

| Database | Berkas backup | Ukuran |
|---|---|---|
| Aplikasi (SQLite dev) | `database/backup-pra-migrasi-20261112-114107.sqlite` | 176.128 byte |
| SEMARAK (MySQL `semarak_oracle`) | `database/backup-pra-migrasi-semarak-20261112-114125.sql` (mysqldump) | 15.023 byte |

Kedua pola berkas backup sudah masuk `.gitignore` (`/database/backup-pra-migrasi-*.sqlite` dan `*.sql`),
jadi tidak akan pernah ikut ter-commit.


### 17.4 Hasil migrasi database dev

`php artisan migrate:status` **sebelum** migrasi: hanya dua migrasi Fase 2 yang Pending
(`2026_11_12_090000_add_anak_link_to_users_table`, `2026_11_12_090100_make_users_email_and_password_nullable`);
seluruh migrasi lain `Ran`. **Tidak ada** migrasi Pending lain di luar rencana.

`php artisan migrate` (hanya dev, tanpa `fresh`/`refresh`): kedua migrasi `DONE`.

Verifikasi read-only setelah migrasi:

| Butir | Hasil |
|---|---|
| `users.anak_id` | ada — `varchar(20)`, nullable |
| `users.graduated_at` | ada — `datetime`, nullable |
| `users.email` / `users.password` | nullable |
| Index | `users_anak_id_unique` (unique), `users_email_unique` (unique), `users_graduated_at_index` (index) |
| Jumlah user | **4 → 4** (tidak berubah) |
| Jumlah baris `semarak.anak` | **7 → 7** (tidak berubah) |
| `alumni()->count()` | 0 (backfill memang belum dijalankan) |
| `php artisan optimize:clear` | sukses (config, cache, compiled, events, routes, views, blade-icons, filament) |

Uji endpoint pada database dev (GET, read-only, lewat kernel Laravel dengan user nyata di dev):
`/admin/anak` → **200**, `/admin/users` → **200**, `/admin/import-data` → **200**, `/klien` → **200**.

Uji rollback dilakukan pada **salinan** SQLite sementara (`database/tmp-rollback.sqlite`, sudah dihapus),
bukan pada database dev: `migrate:rollback` untuk kedua migrasi Fase 2 (`down` masing-masing sukses) lalu
`migrate` kembali (`up` keduanya sukses); jumlah user tetap 4 dan kedua kolom kembali ada. Database dev
tidak pernah di-rollback.

### 17.5 Verifikasi akhir

- `php artisan test` → **139 passed (620 assertions)** (sebelumnya 131; +8 tes pengaman skema).
- `vendor/bin/pint --test` pada seluruh berkas yang berubah → **PASS** (16 berkas).
- `php artisan migrate:status` → kedua migrasi Fase 2 `Ran`.

### 17.6 Urutan rilis PRODUKSI (WAJIB, jangan diubah urutannya)

1. **Audit produksi** — jalankan `docs/AUDIT-PRODUKSI-USERS.md` dan setujui daftar `id` klien yang benar-benar alumni.
2. **Backup database** — dump penuh database aplikasi (dan SEMARAK bila ikut berubah) + simpan di luar server.
3. **Migrasi skema** — `php artisan migrate` (kedua migrasi Fase 2). Belum ada backfill pada langkah ini.
4. **Backfill `graduated_at`** — migrasi berbasis **whitelist `id`** hasil langkah 1 (bukan `UPDATE` massal).
   Belum dijalankan sampai daftar `id` itu disetujui.
5. **Deploy kode** — kode Fase 2–4 (termasuk pengaman skema commit `fb65aa27`) naik setelah skema siap.
6. **Cocokkan angka kartu "Jumlah Alumni"** — bandingkan dashboard klien dengan hasil audit; label
   "Angka sementara (belum final)" harus **hilang** setelah backfill terisi. Bila masih tampil, backfill belum terbaca.
7. **Baru luluskan anak pertama** — jalankan aksi "Luluskan & Aktifkan Akun Klien" setelah keenam langkah di atas
   selesai, lalu periksa email/kata sandi sementara dan status `published` + `graduated_at` akun tersebut.

Alasan urutan ini: skema harus ada sebelum kode baru dipakai (agar tidak terulang error 500 di dev), dan data
alumni harus diaudit sebelum angka publik ditampilkan. Pengaman skema tetap dipertahankan sebagai jaring
pengaman permanen — bukan pengganti urutan rilis di atas.

## 18. Perbaikan mode "Remaja Binaan" & template import resmi (12 Nov 2026)

Lingkup: halaman **Import Data** (mode *Remaja Binaan*), template **Excel & Word** baru, dan tesnya.
Tidak ada perubahan data dev, tidak ada migrasi baru, dan tidak ada berkas modul absensi/font WIP yang disentuh.

### 18.1 Masalah yang ditemukan

| Temuan | Dampak |
|---|---|
| Mode binaan hanya membuat **akun** (`users`, status `Draft`), **tidak** membuat baris `semarak.anak` saat baris tidak ditautkan | Satu baris import tidak menghasilkan satu anak binaan; anak yang belum ada di SEMARAK hilang dari modul absensi/dashboard dan tidak bisa diluluskan (tidak ada `anak_id`) |
| Kolom **Email** diwajibkan pada semua mode | Data lapangan yang tidak punya email (mis. "Kholis") ditolak, padahal akun Draft memang tidak perlu email untuk login |
| Kolom wajib mode binaan (Asal Daerah, Agama, Umur/Tanggal Lahir, Angkatan) tidak ditegakkan | Baris tidak lengkap lolos ke database, data anak menjadi tidak konsisten |
| Template lama: satu sheet/tabel, header di baris 1, tanpa petunjuk, tanpa dropdown, tanpa penanda baris contoh | Operator salah isi format (tanggal, L/P, agama), baris contoh ikut terimpor, dan tidak ada rujukan nilai yang sah |
| Parser lama hanya membaca header baris 1 dan tabel pertama | Template baru (judul + petunjuk di atas tabel) gagal terbaca; template lama harus tetap didukung |

### 18.2 Perubahan alur per baris (mode binaan)

`petaTautanAnak()` tetap menjadi **satu-satunya sumber kebenaran** untuk Preview dan import, tetapi sekarang
di-key berdasarkan **nomor baris** (bukan email) supaya baris tanpa email pun mendapat rencana yang benar.

| Kondisi baris | Hasil import |
|---|---|
| Checkbox "Tautkan ke anak yang sudah ada" **tidak** dicentang (default) | `semarak.anak` **baru** (status `Aktif`) + akun `Draft` (`users.anak_id` terisi, `email`/`password` NULL) |
| Dicentang, nama unik di `anak`, identitas cocok, anak belum punya akun | Akun `Draft` **ditautkan** ke anak yang sudah ada (tanpa membuat anak baru) |
| Dicentang, tetapi nama **kembar** / lebih dari sekali di file / identitas berbeda / anak sudah berakun | Anak **baru** dibuat (sistem tidak menebak); alasan tampil di kolom Rencana |
| Mode **Klien Umum** | Perilaku lama tidak berubah: akun `Published` + kata sandi |

Atomicity per baris: anak baru dibuat `ImportBinaanService::buatAnakBaru()` (pre-check ID + retry maksimum 5 kali
di dalam transaksi, ID dari `AnakService::generateId()`), lalu akun `buatAkunDraft()`. Bila pembuatan akun gagal,
`hapusAnakBaru()` menghapus anak yang baru dibuat (kompensasi) sehingga tidak ada anak tanpa akun, dan baris
dicatat gagal pada ringkasan "Hasil Import" — log hanya memuat nomor baris + pesan, **tanpa PII**.


### 18.3 Template resmi baru

| Berkas | Isi |
|---|---|
| `app/Support/Import/SpesifikasiKolomImport.php` | Definisi tunggal semua kolom (kunci, header, wajib per mode, format, contoh, keterangan, lebar), daftar langkah, aturan umum, catatan privasi, nama instansi, judul template. Dipakai template **dan** parser |
| `app/Support/Import/TemplateExcel.php` | Sheet **PETUNJUK** (judul, langkah, aturan, tabel kolom, catatan privasi), sheet **DATA** (judul, legenda, header berwarna wajib/opsional, baris CONTOH, 200 baris pra-format `=ROW()-4`, zebra, format tanggal/angka, dropdown dari sheet DAFTAR, validasi umur 5–30, batas panjang teks), sheet **DAFTAR** (sumber dropdown, *hidden* + terkunci). Freeze panes `C4`, autofilter, A4 landscape, header/footer cetak, proteksi sheet |
| `app/Support/Import/TemplateWord.php` | **Baru.** A4 landscape; properti dokumen; header (nama instansi + judul); footer "Halaman X dari Y" (`{PAGE}`/`{NUMPAGES}`); halaman PETUNJUK; tabel DATA **tanpa sel gabungan**, header berulang tiap halaman (`tblHeader`), baris CONTOH, 15 baris kosong, zebra |
| `app/Services/ImportTemplateService.php` | **Baru.** `namaBerkas()` (`template-import-remaja-binaan-YYYYMMDD.xlsx/.docx`), `buatExcel()`, `buatWord()`, `referensi()` (agama/jenis kelamin/pendidikan/pelatihan/wilayah dari master + fallback), `daftarDariDatabase()`, `pathSementara()` |
| `app/Services/ImportService.php` | Parser baru: mencari sheet/baris header sampai 40 baris (mendukung template lama header baris 1 dan template baru baris 3), memetakan header beralias, mengabaikan kolom "No" berisi rumus, menolak baris CONTOH, validasi per mode, dedup email, parsing tanggal/umur |

Detail yang dikunci oleh desain:

- Baris contoh memakai penanda literal **`CONTOH - HAPUS SEBELUM IMPORT`**; bila belum dihapus, seluruh file ditolak
  dengan pesan yang menyuruh operator menghapus baris itu (bukan diimpor diam-diam).
- Nama file unduhan bertanggal supaya operator tidak tertukar antar versi template.
- PhpWord menulis teks **tanpa escape** secara default; `TemplateWord::simpan()` menyalakan
  `Settings::setOutputEscapingEnabled(true)` hanya selama pembuatan dokumen (dipulihkan di `finally`) agar karakter
  `&` (mis. pada nama instansi) tidak menghasilkan `.docx` rusak.
- `pendekar.instansi_nama` (env `PENDEKAR_INSTANSI_NAMA`) boleh kosong; template memakai placeholder "Nama Instansi".

### 18.4 Keamanan & privasi

- `users.email` dan `users.password` **nullable** untuk akun Draft: akun tidak bisa login sampai diluluskan.
- Tautan hanya terjadi bila identitas cocok dan tidak ambigu; sistem **tidak pernah** menebak anak.
- Log import tidak memuat nama/email/asal daerah; ringkasan "Hasil Import" di UI hanya menampilkan nomor baris + alasan.
- "Kemungkinan duplikat" tetap **informasi**, bukan penolakan: baris tetap diimpor.
- Query kandidat anak dipotong per 500 parameter (SQLite hanya mendukung 999 variabel per query) dan toleran bila
  koneksi `semarak` gagal (hanya `Log::warning`, import tetap jalan tanpa tautan).
- **Pengaman skema tetap berlaku (§17).** Bila `users.anak_id` belum ada (migrasi Fase 2 tertunda), mode binaan
  jatuh ke `importBinaanTanpaTautan()`: akun `Draft` tetap dibuat, **tanpa** data anak baru, dan alasannya
  dilaporkan lewat `petaTautanAnak()` (`"Migrasi database belum dijalankan…"`). Setelah migrasi dijalankan,
  operator cukup mengulang import; baris lama belum punya anak sehingga tidak menimbulkan data ganda.

### 18.5 Deviasi Fase 3 yang diperbaiki di sini

§15.1 mencatat "Mode Remaja Binaan di Import Data" sebagai **akun Draft** saja, dan implementasi Fase 3 memang
hanya membuat akun. Itu menyimpang dari maksud desain "satu baris = satu remaja binaan": anak yang belum ada di
SEMARAK tidak pernah dibuat. Perbaikan ini mengembalikan maksud tersebut (anak `Aktif` + akun `Draft`), sekaligus
mempertahankan keputusan §15.2 butir 2 (penautan **opt-in**, tidak pernah menebak nama kembar).


### 18.6 Verifikasi

| Butir | Hasil |
|---|---|
| `tests/Feature/Graduation/ImportBinaanTest.php` | 15 tes lulus (anak + akun Draft, baris tanpa email, kolom wajib, umur dari tanggal lahir, aturan tautan, kompensasi, Klien Umum tidak berubah, kata sandi operator diabaikan, kelulusan memakai akun hasil import, judul & checkbox halaman, rencana per baris, duplikat, ringkasan `importSemua`) |
| `tests/Feature/Graduation/TemplateImportTest.php` | **Baru, 8 tes / 67 assertions**: struktur Excel (sheet, header = parser, hidden DAFTAR, proteksi, freeze `C4`, autofilter, dropdown L/P, umur 5–30, tanggal, baris CONTOH, nomor otomatis), struktur Word (properti, tabel DATA tanpa `gridSpan`, `tblHeader`, header instansi, footer `PAGE`/`NUMPAGES`), round-trip Excel & Word (2 baris → 2 anak + 2 akun Draft), penolakan baris CONTOH (Excel & Word), template **lama** Excel & Word tetap bisa diimpor |
| `php artisan test` | **146 passed (730 assertions)**, 0 gagal |
| `vendor/bin/pint --test` (berkas yang berubah) | **PASS** (13 berkas: `app/Support/Import`, `ImportService`, `ImportBinaanService`, `ImportTemplateService`, `ImportData`, `tests/Feature/Graduation`, `tests/Support/Import`) |
| Pemeriksaan manual template | `.xlsx` dibuka ulang dengan PhpSpreadsheet (sheet, header, dropdown, freeze) dan `.docx` dengan PhpWord/`ZipArchive` (header1/footer1, `tblHeader`, tanpa `gridSpan`) — semua sesuai |



## 19. Proteksi area isian template Excel & desain ulang dashboard Admin/Klien (12 Nov 2026)

Lingkup: `app/Support/Import/TemplateExcel.php` + `tests/Feature/Graduation/TemplateImportTest.php` (Pekerjaan 1), lalu
dashboard **Admin** (`/admin`) dan **Klien** (`/klien`) beserta tesnya (Pekerjaan 2). Tidak ada perubahan skema, tidak
ada backfill `graduated_at`, dan **tidak ada** berkas modul absensi/font WIP yang disentuh.

### 19.1 Bug: sel sheet DATA template Excel tidak bisa diedit

| Temuan | Dampak |
|---|---|
| Sheet DATA diproteksi, tetapi **hanya baris 5-204** yang di-`UNPROTECTED`; baris CONTOH (baris 4) tetap mewarisi default → terkunci | Operator yang memilih/mengubah/menghapus baris CONTOH mendapat pesan Excel *"The cell or chart you're trying to change is on a protected sheet"*; file tidak pernah bisa diimpor karena baris CONTOH wajib dihapus |
| Izin aksi pada sheet terproteksi tidak dibuka (`autoFilter`, `sort`, `formatColumns/Rows`, `insertRows`, `deleteRows`, `selectLockedCells`) | Autofilter, pengurutan, pengaturan lebar kolom, serta sisip/hapus baris ditolak Excel walau areanya sudah tidak terkunci |
| Header kolom hanya "mewarisi" default (bukan dikunci eksplisit) | Perilaku terkunci bergantung pada default gaya sel; tidak eksplisit di berkas |

Perbaikan (`TemplateExcel::tulisData()` & `TemplateExcel::pasangCetakDanProteksi()`):

1. **Header (baris 3) dikunci eksplisit** (`Locked = PROTECTED`) supaya nama/urutan kolom yang dibaca parser tidak tertimpa.
2. **Kolom No (A4:A204) tetap `PROTECTED`** karena berisi rumus `=ROW()-4`.
3. **Area isian B4:S204 di-`UNPROTECTED`** — sekarang **termasuk baris CONTOH**, sehingga operator bisa mengedit,
   menimpa, dan menghapus baris contoh.
4. **Izin aksi dibuka** (`false` = diizinkan): `autoFilter`, `sort`, `formatColumns`, `formatRows`, `insertRows`,
   `deleteRows`, `selectLockedCells`, `selectUnlockedCells`. Hasil di XML:
   `<sheetProtection sheet="1" formatColumns="0" formatRows="0" insertRows="0" deleteRows="0" sort="0" autoFilter="0" selectLockedCells="0" selectUnlockedCells="0"/>`.
5. Sheet **PETUNJUK** dan **DAFTAR** tetap terproteksi penuh (sumber dropdown tidak boleh diubah).
6. **Dropdown/validasi tidak berubah** — 11 `dataValidation` tetap terpasang setelah perubahan proteksi.
7. **Template Word** diperiksa: `.docx` tidak memuat `documentProtection` (tidak ada padanan "protected sheet" di Word,
   dan proteksi akan memblokir pengisian tabel DATA). Dikunci oleh tes baru.
8. **Mode Klien Umum memakai berkas template yang sama** (satu `TemplateExcel`; perbedaan mode hanya pada kolom wajib
   yang ditandai `*`), jadi perbaikan ini otomatis berlaku untuk kedua mode.

Tes regresi baru di `tests/Feature/Graduation/TemplateImportTest.php`:

| Tes | Isi |
|---|---|
| `test_area_isian_sheet_data_terbuka_dan_izin_aksi_lengkap` | (a) `B4` dan `B5`/`C5`/`K204`/`S204` berstatus `UNPROTECTED`; (b) header baris 3 dan kolom No (`A5`, `A204`) `PROTECTED` saat sheet diproteksi; (c) PETUNJUK & DAFTAR terproteksi; (d) 8 izin aksi terbuka; (e) dropdown/validasi & autofilter masih ada |
| `test_template_word_tidak_memakai_proteksi_dokumen` | `word/settings.xml` & `word/document.xml` tidak memuat `documentProtection` |

Commit Pekerjaan 1: **`53f25bab` - `fix(import): area isian template Excel tidak boleh terkunci`**.


### 19.2 Dashboard Admin (`/admin`)

| Berkas | Peran |
|---|---|
| `app/Support/StatistikAlumni.php` | **Baru.** Satu-satunya tempat menghitung angka alumni (`alumni()->count()`, fallback `client + published` + `belum_final`) — dipakai dashboard Admin **dan** Klien supaya angkanya tidak pernah berbeda |
| `app/Services/DasborAdminService.php` | **Baru.** Semua angka dashboard: `anak_aktif`, `siap_diluluskan`, `akun_draft`, `alumni`, `total_klien`, `admin`, grafik (binaan per angkatan, komposisi status, pertumbuhan 12 bulan), dan `perlu_tindakan`. Satu cache **5 menit** (`dashboard:admin:ringkasan:v1`), hanya COUNT/GROUP BY lintas database, tanpa N+1 |
| `app/Filament/Widgets/AdminStatsOverview.php` | 6 kartu: Anak Binaan Aktif, Siap Diluluskan, Akun Draft (dengan tautan terfilter), Alumni (dengan label "Angka sementara (belum final)"), Total Akun Klien, Admin |
| `app/Filament/Widgets/BinaanPerAngkatanChart.php` | Grafik **batang** 10 angkatan terbesar |
| `app/Filament/Widgets/KomposisiStatusAkunChart.php` | Grafik **donat** Draft/Aktif/Diarsipkan |
| `app/Filament/Widgets/PertumbuhanAkunChart.php` | Grafik **garis** 12 bulan akun Klien baru (satu query `GROUP BY`, ekspresi bulan menyesuaikan driver) |
| `app/Filament/Widgets/PerluTindakanWidget.php` + `resources/views/filament/widgets/perlu-tindakan.blade.php` | Panel "Perlu Tindakan": akun Draft, status migrasi Fase 2, anak aktif tanpa akun Klien — hanya jumlah + tautan terfilter |
| `app/Filament/Widgets/AksesCepatWidget.php` + `resources/views/filament/widgets/akses-cepat.blade.php` | Import Data, Anak Binaan, User, Kegiatan |
| `app/Filament/Resources/UserResource.php` | Filter baru `status` & `user_type` (dipakai tautan `?tableFilters[status][value]=draft`) |
| `app/Filament/Resources/AnakResource.php` | Filter baru "Belum punya akun Klien" (hanya dipasang bila `users.anak_id` sudah ada) |
| `app/Providers/Filament/AdminPanelProvider.php` | Registrasi 6 widget baru (urutan via `$sort`) |

Keputusan penting:

- **Definisi "Siap Diluluskan"**: anak `Aktif` pada angkatan yang `masa_pembinaan.tanggal_selesai <= hari ini` —
  memakai aturan yang sama dengan `PembinaanService::luluskanAngkatanSelesai()`, bukan ambang umur karangan.
  Bila tabel/koneksi belum ada -> `null` -> kartu menampilkan **"Data belum tersedia"**.
- **Privasi**: seluruh data dashboard adalah agregat; tidak ada nama/email pengguna yang dibaca atau dikirim ke view
  (dikunci tes).
- **Ketahanan**: semua query lintas database `semarak` dibungkus `try/catch`; bagian yang gagal menjadi `null`
  ("Data belum tersedia"), bukan 500. Kolom Fase 2 tetap lewat `SchemaGuard` (pola Â§17).
- **Kinerja**: widget dirender **non-lazy** (`$isLazy = false`) supaya angka tampil pada paint pertama — aman karena
  hasilnya berasal dari cache 5 menit.
- **Urutan & lebar widget** (semua `$sort >= 1` agar kartu sambutan bawaan `AccountWidget` dengan `sort = -3` tetap
  paling atas): `1` AdminStatsOverview (lebar penuh, 6 kartu), `2` grafik batang binaan (lebar penuh), `3` grafik
  donat komposisi status (`1` kolom) berdampingan dengan `4` grafik garis pertumbuhan (`lg` 2 kolom), `5` Perlu
  Tindakan (lebar penuh — memuat 3 panel `md:grid-cols-3`), `6` Akses Cepat (lebar penuh).
- **Catatan CSS**: panel Admin/Klien memakai CSS bawaan Filament (tanpa tema Tailwind kustom), sehingga kelas warna
  arbitrer (`bg-amber-50`, `md:grid-cols-3`, dst.) tidak ikut ter-compile. Karena itu warna pada panel baru memakai
  **inline style** dan hanya kelas layout yang benar-benar ada di `filament/dist/theme.css`.

### 19.3 Dashboard Klien (`/klien`)

| Bagian | Isi |
|---|---|
| Hero | "Selamat Datang" + nama pemilik akun, **status akun** (Aktif / Menunggu aktivasi / Diarsipkan), bar kelengkapan profil |
| Kartu ringkasan | **Jumlah Alumni** (label "Angka sementara (belum final)" selama backfill belum berjalan), **Pelatihan Saya**, **Kelengkapan Profil** (persen + progress bar) |
| Kelengkapan Profil | Persen + daftar **kolom yang masih kosong** + tombol **"Lengkapi Profil"**. Persentase & daftar dihitung dari satu sumber (`Dashboard::FIELD_PROFIL`), jadi tidak mungkin berbeda |
| Pelatihan Saya | Daftar pelatihan **milik sendiri** (read-only) + keadaan kosong "Belum ada data pelatihan" |
| Akses Cepat | Hanya **Ubah Profil** & **Data Pelatihan**; **Data Alumni** hanya bila `pendekar.alumni.directory_enabled` = true |

Perubahan pendukung: `statistikAlumni()` kini mendelegasikan ke `App\Support\StatistikAlumni` (perilaku & label tidak
berubah), ditambah `getKolomProfilKosong()`, `getPelatihanSaya()`, `getStatusAkun()`, dan `getTautanCepat()`.
PRIVASI: dashboard tetap tidak pernah menampilkan nama/email/telepon orang lain dan tidak menambah tabel orang lain
(`tests/Feature/Security/ClientDashboardPrivacyTest.php` tetap hijau tanpa perubahan).


### 19.4 Tes & verifikasi Pekerjaan 2

| Berkas tes | Cakupan |
|---|---|
| `tests/Feature/Dashboard/AdminDashboardTest.php` (5 tes) | `/admin` 200 untuk admin; **tidak** memuat nama/email pengguna lain (admin kedua & klien); angka ringkasan sesuai data; "Data belum tersedia" saat database SEMARAK belum siap; **klien tidak bisa membuka `/admin`** (403) |
| `tests/Feature/Dashboard/AdminDashboardSchemaGuardTest.php` (3 tes) | (`AbsensiTestCase`) `/admin` tetap 200 & jujur saat `users.anak_id`/`graduated_at` dilepas (pola `PengamanSkemaFase2Test`); angka binaan/siap diluluskan/"tanpa akun" diambil dari `semarak`; cache 5 menit benar-benar dipakai & `lupakan()` menyegarkan |
| `tests/Feature/Dashboard/ClientDashboardTest.php` (7 tes) | Sapaan + status akun; tidak memuat data/pelatihan klien lain; persentase kelengkapan profil 20% -> 40% beserta daftar kolom kosong; pelatihan read-only + keadaan kosong; **urutan bagian** (hero sebelum kartu ringkasan sebelum pelatihan sebelum akses cepat — penjaga regresi tata letak); akses cepat Alumni mengikuti config |

| Verifikasi | Hasil |
|---|---|
| `php artisan test` (seluruh suite) | **163 passed (846 assertions)**, 0 gagal — termasuk `ClientDashboardPrivacyTest`, `AlumniStatistikDashboardTest`, `ClientPanelMenuTest`, `PengamanSkemaFase2Test`, `TemplateImportTest`, serta seluruh tes modul Absensi (dashboard absensi **tidak berubah**) |
| `vendor/bin/pint --test` (17 berkas berubah/baru) | **PASS** — `pint` hanya merapikan gaya (line ending, urutan `use`, `unary_operator_spaces`, docblock `Collection<int, MasterTraining>`); tidak ada perubahan perilaku |
| `php artisan tinker` pada database dev (MySQL) | `DasborAdminService::ringkasan()` berjalan tanpa error pada skema nyata: `anak_aktif=6`, `siap_diluluskan=0`, `alumni=1 (belum_final=false)`, `total_klien=2`, `admin=2`, grafik 12 bulan terisi |
| Tangkapan layar | Tidak tersedia: tidak ada browser headless di lingkungan ini (Playwright/Puppeteer tidak terpasang, `node_modules/.bin/playwright` tidak ada). Verifikasi visual dilakukan lewat markup + kelas CSS yang sudah dipastikan ada di `filament/dist/theme.css` |

Catatan: dashboard modul Absensi (`/absensi`), `routes/absensi.php`, `ApiController`, `EnsureAbsensiAdmin`,
`database/migrations/semarak/*`, serta aset self-host font/WIP absensi **tidak disentuh** sama sekali oleh Pekerjaan 1
maupun Pekerjaan 2.

---

## 20. Formulir Word per anak (Pekerjaan 3)

Formulir A4 portrait berisi **satu blok per halaman** (tabel dua kolom `Label | Isian`) untuk pendataan remaja
binaan. Berbeda dari template Word horizontal (bagian 18) yang memuat banyak baris pada satu tabel landscape; keduanya
tetap didukung parser.

### 20.1 Berkas

| Berkas | Perubahan |
|---|---|
| `app/Support/Import/TemplateFormulirWord.php` (baru) | Pembuat formulir: halaman 1 PETUNJUK (langkah, aturan, label wajib, privasi), blok CONTOH, 5 blok kosong; A4 portrait; header instansi + judul, footer "Halaman X dari Y" + catatan privasi; font isian Arial 10 |
| `app/Support/Import/SpesifikasiKolomImport.php` | Sumber tunggal `judulFormulir()`, `subjudulFormulir()`, `langkahPengisianFormulir()`, `aturanUmumFormulir()`; label & urutan kolom tetap dari `kolom()`/`header()` |
| `app/Services/ImportService.php` | `parseWord()` mengenali **dua format**: tabel horizontal lama (baris header kolom) dan formulir dua kolom; nomor blok dari judul `BLOK n` (fallback urutan); tabel kosong diabaikan; `barisBerisiData()` memakai header (bukan kunci snake_case); galat/duplikat membawa `blok` |
| `app/Services/ImportTemplateService.php` | `namaBerkasFormulir()` = `template-formulir-remaja-binaan-YYYYMMDD.docx`, `buatFormulirWord()` |
| `app/Filament/Pages/ImportData.php` + `resources/views/filament/pages/import-data.blade.php` | Tombol **Download Formulir Word (per anak)**; tabel Preview menampilkan **Blok n** (bukan nomor baris) untuk file formulir |
| `tests/Feature/Graduation/FormulirWordTest.php` (baru) | 6 tes: struktur, round-trip 2 blok, CONTOH ditolak, label wajib kosong, template lama 2 kolom, mode klien |

### 20.2 Keputusan penting

- **Blok CONTOH** (`BLOK 1 - CONTOH`) memakai `SpesifikasiKolomImport::barisContoh()`; isian Nama berisi
  `CONTOH - HAPUS SEBELUM IMPORT`, sehingga file yang lupa dikosongkan ditolak dengan penanda yang sama seperti Excel.
- **Blok kosong diabaikan**: tabel yang seluruh isiannya kosong tidak menjadi baris data dan tidak menghasilkan galat.
  Hanya blok yang berisi minimal satu kolom data yang divalidasi (label wajib kosong -> galat).
- **Nomor blok**: parser membaca judul paragraf `BLOK n` (bukan urutan tabel), sehingga Preview menyebut "Blok 3"
  pada file formulir dan tetap nomor baris pada file tabel.
- **Kompatibilitas**: tabel horizontal lama (termasuk yang hanya dua kolom, mis. Nama + Email) dideteksi lebih dulu;
  template Excel, template Word horizontal, dan perilaku mode Klien Umum tidak berubah.
- **Font**: seluruh isian Arial 10 (label 9pt tebal). Dokumen tidak diproteksi (Word tidak punya padanan protected sheet).

### 20.3 Tes & verifikasi

| Verifikasi | Hasil |
|---|---|
| `php artisan test` (seluruh suite) | **169 passed (1016 assertions)**, 0 gagal (163 tes sebelumnya + 6 tes formulir) |
| `vendor/bin/pint --test` pada 6 berkas yang diubah/dibuat | **PASS** |
| Render PDF | **Tidak dilakukan**: LibreOffice tidak terpasang di mesin ini. Alternatif Microsoft Word COM dicoba tetapi tidak selesai (proses otomatis dihentikan), sehingga verifikasi visual memakai struktur XML `.docx` (A4 portrait, header/footer, jumlah tabel, Arial 10) |

Catatan: formulir ini ditujukan untuk **mode Remaja Binaan**; mode Klien Umum tetap memakai template Excel/Word biasa
(aturan email wajib tidak berubah).

