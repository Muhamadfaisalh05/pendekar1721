# Petunjuk Audit Produksi — Tabel `users`

**Tujuan:** memverifikasi data klien/alumni di database **produksi/staging** sebelum
`users.graduated_at` di-backfill, karena di mesin pengembangan hanya ada 4 user
(klaim "500+ klien/alumni" **belum terkonfirmasi**, lihat
`BACKUP-LOGIC-SEBELUM-REFACTOR.md` §12).

**Sifat skrip: READ-ONLY.** `docs/audit/audit-users.php` hanya berisi `SELECT`
dan agregasi (`COUNT`, `GROUP BY`, `pluck`). Tidak ada `INSERT`, `UPDATE`,
`DELETE`, `Schema::create/table/alter`, maupun migrasi. Verifikasi sendiri
sebelum menjalankan:

```bash
grep -nE "insert|update|delete|drop|truncate|Schema::" docs/audit/audit-users.php
# keluaran yang diharapkan HANYA:
#   - baris komentar/teks mode (baris 7-8, 53)
#   - Schema::hasColumn(...) pada baris 124/131/163  (cek kolom, tidak mengubah skema)
# TIDAK boleh ada DB::table(...)->insert/update/delete maupun Schema::table/create
```

---

## 1. Prasyarat

| Syarat | Keterangan |
|---|---|
| Akses shell ke server produksi/staging | SSH, atau Laragon/CLI setara |
| PHP CLI | versi sama dengan aplikasi (8.4) |
| `.env` server | sudah menunjuk database **yang ingin diaudit** |
| Kuota baca DB | skrip hanya membaca; aman dijalankan saat jam sibuk, tetapi lebih baik di luar jam sibuk |

## 2. Langkah 0 — pastikan target database benar (WAJIB)

Jalankan di server, di direktori aplikasi:

```bash
php artisan about | head -n 20
php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo config("database.default"), " -> ", DB::connection()->getDatabaseName(), PHP_EOL;
echo "APP_ENV=", config("app.env"), PHP_EOL;'
```

Periksa bahwa `APP_ENV=production` (atau `staging`) dan nama database sesuai.
**Jika masih menunjuk database lokal, hentikan dan betulkan `.env` lebih dulu.**

## 3. Langkah 1 — pastikan skrip tersedia di server

Skrip sudah ikut repository, jadi cukup sinkronkan kode (mis. `git pull`) lalu cek:

```bash
ls -l docs/audit/audit-users.php
```

Bila belum ada, unggah manual:

```bash
scp docs/audit/audit-users.php user@server:/path/ke/aplikasi/docs/audit/
```

## 4. Langkah 2 — jalankan audit

### Linux / macOS (server produksi)

```bash
php artisan tinker --execute="require base_path('docs/audit/audit-users.php');" \
  2>&1 | tee audit-users-prod.txt
```

### Windows PowerShell (mis. staging di server Windows)

```powershell
php artisan tinker --execute="require base_path('docs/audit/audit-users.php');" 2>&1 |
  Tee-Object -FilePath audit-users-prod.txt
```

### Bila `tinker` tidak tersedia/diizinkan di produksi

```bash
php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require "docs/audit/audit-users.php";' 2>&1 | tee audit-users-prod.txt
```

### Menyamarkan email (PII) saat hasil akan dibagikan

```bash
AUDIT_MASK_PII=1 php artisan tinker --execute="require base_path('docs/audit/audit-users.php');" \
  2>&1 | tee audit-users-prod.txt
```

PowerShell: `$env:AUDIT_MASK_PII=1` sebelum perintah dijalankan.

## 5. Langkah 3 — kirim hasilnya

Kirimkan isi `audit-users-prod.txt` (atau tempelkan di percakapan). Bagian yang
paling menentukan untuk keputusan backfill:

1. `TOTAL users` dan tabel `user_type x status`.
2. `client: created_by` — berapa akun tanpa pemilik.
3. `client: tahun/bulan created_at` — sebaran akun lama.
4. `client: 15 data terbaru` — contoh akun, plus `created_by` dan tanggal buat.
5. `email duplikat (case-insensitive)` — harus kosong; kalau ada, backfill ditunda.
6. `password NULL / kosong`.
7. `kesiapan definisi alumni` — jumlah `published` dengan/tanpa `graduated_at`
   (hanya muncul bila migrasi Fase 2 sudah diterapkan di server).
8. `semarak.anak` — jumlah anak per status/angkatan (untuk mencocokkan calon alumni).
9. `koneksi & driver` — memastikan audit benar-benar mengenai database yang dituju.

> Hasil audit berisi email asli. Simpan sebagai file terbatas akses
> (`chmod 600 audit-users-prod.txt`) dan hapus setelah dianalisis.

## 6. Setelah hasil diterima

| Temuan | Tindakan |
|---|---|
| Semua `client.published` memang alumni | Backfill `graduated_at` dijalankan (migrasi data terpisah, baru dibuat setelah audit) |
| Sebagian akun ternyata bukan alumni (mis. akun staf/kios) | Backfill dibatasi lewat daftar `id` yang disetujui, bukan `UPDATE` massal |
| Email duplikat ditemukan | Bereskan duplikat lebih dulu (email `unique` bisa menggagalkan migrasi) |
| `created_by` NULL mendominasi | Kepemilikan tidak dipakai sebagai penanda alumni (keputusan final: `graduated_at` saja) |

> **Status 12 November 2026 (Fase 4).** Kartu **Jumlah Alumni** di dashboard klien memakai definisi resmi
> `App\Models\User::scopeAlumni()` (`user_type = 'client'` AND `status = 'published'` AND
> `graduated_at IS NOT NULL`) **segera setelah** ada minimal satu akun yang memenuhi definisi itu
> (tes: `tests/Feature/Graduation/AlumniStatistikDashboardTest.php`).
>
> Selama backfill belum dijalankan (belum ada satu pun `graduated_at` terisi), kartu memakai **fallback
> sementara**: angka dihitung dengan definisi lama `client` + `published` dan diberi label
> "Angka sementara (belum final)". Peralihan ke definisi resmi terjadi otomatis pada request berikutnya
> setelah `graduated_at` pertama terisi — tanpa deploy ulang.
>
> Migrasi backfill berbasis daftar `id` yang disetujui **belum dibuat**; hasil audit inilah yang
> menentukan daftar `id` tersebut.

## 7. Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| `Maximum execution time` / memory habis | Jalankan dengan `php -d memory_limit=512M artisan tinker ...` |
| `could not find driver` | Ekstensi PHP (`pdo_mysql`) belum aktif di server |
| `Access denied for user` | Kredensial `.env` server salah; jangan paksakan, minta kredensial baca |
| Skrip berhenti tanpa output | Pastikan `--execute` memakai tanda kutip lurus (`'` / `"`), bukan kutip miring |
| Bagian `semarak.anak` error | Koneksi `SEMARAK_DB_*` belum diisi di server; bagian lain tetap jalan |
