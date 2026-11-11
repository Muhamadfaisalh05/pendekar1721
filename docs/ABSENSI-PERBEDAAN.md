# Perbedaan Perilaku: Native SEMARAK vs Modul Laravel

Dokumen ini mencatat **semua** perbedaan yang diketahui beserta status persetujuan.
Oracle: 46 fixture di `tests/Fixtures/Absensi/oracle/` (dua kali capture, hash identik).

## A. Disetujui pemilik produk

| # | Perubahan | Alasan | Dampak |
|---|---|---|---|
| A1 | Baris detail `summary`/`report` dipetakan berdasarkan **id kegiatan**, bukan `id - 1` | Native memakai indeks array sehingga nilai hilang bila id kegiatan berlubang (`backend.php:286-289`) | Identik untuk data sekarang (id 1..22); benar untuk data berlubang |
| A2 | Zona waktu aplikasi **`Asia/Jakarta`** (native memakai zona server) | Menghilangkan bug beda hari (server UTC vs WIB) pada tanggal default evaluasi/rapor | Tanggal "hari ini" konsisten dengan jam dinding WIB |
| A3 | Akses `/absensi` hanya untuk `user_type = admin` | Native tanpa autentikasi sama sekali | Tamu diarahkan ke `/admin/login`, klien mendapat 403 |

## B. Perubahan karena keamanan (bukan karena kontrak)

| # | Perubahan | Catatan |
|---|---|---|
| B1 | Semua rute modul di balik `auth` + CSRF | Frontend mengirim `X-CSRF-TOKEN`; tidak ada 419 |
| B2 | Rate limit 120/menit (baca) dan 30/menit (tulis) | Respons 429 memakai format Laravel, bukan envelope native |
| B3 | Pesan error 500 dibuat generik (`Terjadi kesalahan pada server.`) dan detail masuk log | Native membocorkan pesan SQLSTATE ke klien |
| B4 | `semarak_init_schema()` (DDL + seeding setiap request) **tidak** direplikasi | Diganti migrasi parity + `PembinaanService::luluskanAngkatanSelesai()` (hanya UPDATE status) |
| B5 | Kredensial DB tidak lagi hardcoded | Pindah ke `SEMARAK_DB_*` di `.env` |
| B6 | CDN Tailwind/FontAwesome diganti build Vite lokal; ikon memakai SVG inline | Tanpa CDN sesuai syarat |
| B7 | Inline `onclick` pada halaman diganti event listener | `toggleAccordion` → `data-accordion` |

## C. Perilaku native yang dipertahankan sengaja

| # | Perilaku | Bukti fixture |
|---|---|---|
| C1 | Envelope tidak seragam (list = array mentah) | `01`, `05`, `06` |
| C2 | Hanya 200/400/404/500 (tanpa 405/422) | `20`, `46` |
| C3 | `POST` ke action GET tetap dilayani | `45` |
| C4 | `mulai > sampai` → 400 untuk semua action | `19` |
| C5 | Batch gagal sebagian → rollback penuh | `28` → `29` |
| C6 | `libur` tetap nilai evaluasi; tidak ada tabel izin terpisah | `15`, `21` |
| C7 | Dashboard: `total = count(evaluasi) − libur`, pembagi minimal 1 | `15` |
| C8 | `report` menghitung ringkasan dengan rentang asli sebelum default tanggal | `12` |
| C9 | `report` untuk anak tidak ditemukan → `"anak": false` | `14` |
| C10 | Jadwal **hanya** ditegakkan di klien (tombol dinonaktifkan), server tetap menerima POST | `absensi.php` `isActivityScheduled()` |
| C11 | `settings` mengembalikan `jadwal: []` bila belum ada jadwal | `08` |
| C12 | ID anak auto `AN-00X`; format ID divalidasi regex | `30`, `31` |

## D. Fitur yang TIDAK diimplementasikan (tidak ada di sumber)

Setelah audit, aplikasi sumber yang tersedia (`semarak`) **tidak memiliki**:

1. Aturan pengecualian shalat untuk anak non-Muslim (`stripos 'shalat'`) — tidak ada kode terkait.
2. Impor Excel/Word di browser (SheetJS/mammoth) — tidak ada dependensi/kode.
3. Tabel `daily_absences`, `is_active`, `activities.sort_order`, `evaluations.notes` — skema berbeda (izin disimpan sebagai `libur`).

Karena itu modul mengikuti skema & fitur **yang benar-benar ada**, tanpa menambah
kontrak baru. Bila fitur tersebut dibutuhkan, harus dibangun sebagai fitur baru
(disetujui terpisah), bukan bagian dari "parity".

## E. Temuan terbuka (belum diubah)

| # | Temuan | Rekomendasi |
|---|---|---|
| E1 | Jadwal kegiatan tidak ditegakkan di server: `save_evaluasi` menerima kegiatan yang tidak dijadwalkan pada tanggal tersebut | Tambahkan validasi server (opsional, mengubah perilaku) |
| E2 | Pengguna dapat menandai `libur` untuk kegiatan apa pun tanpa alasan tercatat | Tambah kolom alasan/audit bila diperlukan |
| E3 | `semarak` menampilkan status `Lulus` otomatis saat tanggal selesai pembinaan lewat; data lama tidak dapat dikembalikan otomatis | Sudah direplikasi; pertimbangkan tombol "batalkan kelulusan" |
| E4 | Halaman pengaturan mengirim seluruh jadwal setiap kali simpan | Bisa dioptimalkan menjadi per-baris (bukan perubahan perilaku) |
