# Fixture Oracle — Modul Absensi (SEMARAK)

Fixture di folder `oracle/` adalah **snapshot respons asli aplikasi native SEMARAK**
(`c:\laragon\www\Dinsos\semarak`) dan menjadi *oracle* (sumber kebenaran perilaku)
untuk uji karakterisasi modul Laravel di `app/Modules/Absensi`.

## Sumber & isolasi

- Kode sumber **tidak diubah**. Dibuat salinan read-only di luar repo:
  `c:\laragon\www\Dinsos\_oracle\semarak`.
- Pada salinan itu hanya dua hal yang diubah (khusus lingkungan oracle):
  1. `includes/db.php` → `DB_NAME = 'semarak_oracle'`.
  2. `TEXT NOT NULL DEFAULT ""` → `TEXT NOT NULL` (MySQL 8 menolak default literal pada kolom TEXT;
     dump asli dibuat di MariaDB 10.4).
- Database oracle: `semarak_oracle`, di-reset dari `semarak/db/semarak.sql`
  (6 anak, 22 kegiatan, 50 evaluasi) sebelum setiap capture.

## Cara membuat ulang

```powershell
# 1. salin aplikasi (sekali saja)
robocopy 'c:\laragon\www\Dinsos\semarak' 'c:\laragon\www\Dinsos\_oracle\semarak' /E /XD .git

# 2. sesuaikan DB_NAME di salinan -> semarak_oracle, lalu jalankan server
php -S 127.0.0.1:8123 -t c:\laragon\www\Dinsos\_oracle\semarak

# 3. capture (mereset DB oracle lebih dulu agar deterministik)
php tests/Support/Absensi/capture-oracle.php http://127.0.0.1:8123
```

Capture dijalankan dua kali berturut-turut dan menghasilkan **46/46 body identik**
(hash sha256 sama), sehingga fixture aman dipakai sebagai oracle.

## Cakupan (46 skenario)

- **Baca (20):** `anak`, `anak_list`, `anak_detail` (ada/tidak), `kegiatan`,
  `evaluasi` (per tanggal & tanggal invalid), `settings`, `summary` (default id,
  rentang, anak tidak ada), `report` (default, rentang, anak tidak ada),
  `dashboard` (polos, filter angkatan/rentang/status, rentang terbalik),
  action tidak dikenal.
- **Tulis (26):** `save_evaluasi` (valid + 3 kasus invalid), `save_evaluasi_batch`
  (valid, kosong, gagal sebagian + verifikasi rollback), `anak_store`
  (valid/duplikat/nama kosong), `anak_update` (valid/tidak ada), `anak_delete`
  (valid/tidak ada), `settings_save` (valid + aturan/hari/rentang invalid),
  `report_notes_save` (valid/periode invalid), verifikasi persistensi setelah tulis,
  serta perilaku method (POST ke action GET, GET ke action POST).

## Perilaku native yang terkunci oleh oracle (jangan "diperbaiki" tanpa persetujuan)

1. **Envelope tidak seragam**: list `anak`/`kegiatan`/`evaluasi` mengembalikan
   **array mentah**; aksi tulis mengembalikan `{success, message, ...}`.
2. **Status**: hanya `200 / 400 / 404 / 500`. **Tidak ada 405/422.**
3. **POST ke action GET** tetap dilayani (mis. `POST ?action=dashboard` → 200 data dashboard).
4. **`libur` adalah nilai evaluasi**, bukan tabel izin terpisah; dashboard menghitung
   `total = count(evaluasi) - libur` dengan pembagi minimal 1.
5. **Rentang terbalik** (`mulai > sampai`) → 400 untuk semua aksi (validasi global di `api.php`).
6. **`summary` memetakan baris detail dengan `$detail[$kegiatan_id - 1]`** — hanya benar
   bila id kegiatan berurutan 1..N. Ini bug laten: kegiatan dengan id di luar rentang
   (mis. hasil seeding setelah penghapusan) tidak akan terhitung. **Belum diperbaiki**
   karena mengubah output; menunggu keputusan pemilik produk.
7. **Tanpa auth/CSRF** dan schema di-`CREATE TABLE IF NOT EXISTS` + seeding pada setiap
   koneksi (`semarak_pdo()`), serta `semarak_init_schema()` meng-update status `Lulus`
   berdasarkan `masa_pembinaan.tanggal_selesai <= CURDATE()`.

## Struktur file

```
oracle/
├── manifest.json          # daftar skenario + status + sha256 body
├── 01-get-anak.json
├── ...
└── 46-get-ke-action-post.json
```

Setiap file: `request` (method, uri, payload) + `response` (status, headers, body mentah, json ter-parse).
