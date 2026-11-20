# Deploy Modul Absensi pada Domain Tunggal

Satu vhost/domain: `/` → pendekar1721, `/absensi/*` → modul absensi (codebase yang sama).

## 1. Persiapan

```powershell
git fetch origin
git checkout feat/modul-absensi        # atau main setelah PR digabung
composer install --no-dev --optimize-autoloader
npm ci
```

Pastikan `.env` produksi memuat:

```env
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta
APP_URL=https://<domain-kominfo>
APP_FORCE_SCHEME=https

DB_CONNECTION=mysql
DB_DATABASE=pendekar1721
DB_USERNAME=...
DB_PASSWORD=...

SEMARAK_DB_CONNECTION=mysql
SEMARAK_DB_HOST=127.0.0.1
SEMARAK_DB_PORT=3306
SEMARAK_DB_DATABASE=semarak_db
SEMARAK_DB_USERNAME=...
SEMARAK_DB_PASSWORD=...

# Sesi & rate limit (login)
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=database            # WAJIB: rate limit login butuh cache persisten

# Isi hanya bila aplikasi di balik reverse proxy/load balancer (IP/CIDR, pisah koma).
# Kosong = header X-Forwarded-For tidak dipercaya (anti spoofing IP).
TRUSTED_PROXIES=
```

> Jangan commit `.env`. File ini sudah di-`.gitignore` dan tidak lagi di-track git.

## 2. Backup (wajib sebelum migrate)

```powershell
mysqldump -u root -p --single-transaction --routines --triggers semarak_db  > backup-semarak-$(Get-Date -Format yyyyMMdd-HHmm).sql
mysqldump -u root -p --single-transaction --routines --triggers pendekar1721 > backup-pendekar-$(Get-Date -Format yyyyMMdd-HHmm).sql
```

## 3. Migrasi

```powershell
# review dulu
php artisan migrate --pretend --database=semarak --path=database/migrations/semarak

# jalankan (guard hasTable: aman untuk DB yang sudah berisi data)
php artisan migrate --database=semarak --path=database/migrations/semarak

# migrasi aplikasi utama (bila ada)
php artisan migrate --force
```

Catatan: **jangan** memakai `migrate:fresh` pada database produksi.

## 4. Build & cache

```powershell
npm run build
php artisan optimize           # config + route + view cache
php artisan filament:optimize  # opsional, komponen Filament
```

> Tabel `cache` & `cache_locks` (migrasi `0001_01_01_000001_create_cache_table.php`) harus ada
> karena `CACHE_STORE=database` dipakai untuk rate limit login. Jika belum, jalankan
> `php artisan migrate --force` sebelum mengaktifkan `CACHE_STORE=database`.

> **Aset tanpa CDN.** Font Inter sudah ikut di repositori di `public/fonts/inter/`
> (`InterVariable.woff2`, `InterVariable-Italic.woff2`, `LICENSE.txt`) dan dideklarasikan
> lewat `resources/sass/_fonts.scss`. Tidak ada request ke `rsms.me`/Google Fonts saat
> runtime, jadi halaman tetap tampil benar di jaringan tertutup. Pastikan web server
> menyajikan `*.woff2` dengan `Content-Type: font/woff2` dan cache `immutable`:

```nginx
location ~* \\.woff2$ {
    add_header Cache-Control "public, max-age=31536000, immutable";
    types { font/woff2 woff2; }
}
```

## 5. Web server

Document root **wajib** ke folder `public/` pendekar1721 (bukan ke root proyek,
bukan ke folder `semarak/`).

Contoh Nginx:

```nginx
server {
    listen 443 ssl http2;
    server_name <domain-kominfo>;
    root /var/www/pendekar1721/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

## 6. Smoke test setelah deploy

1. `GET /` → beranda pendekar1721 tampil (tidak berubah).
2. `GET /login` → halaman login terpadu tampil; login **admin** → diarahkan ke `/admin`.
   `/admin/login` dan `/klien/login` harus **404** (satu pintu: `/login`).
3. Login **admin absensi** (`admin_absensi`) → diarahkan ke `/absensi`; membuka `/admin` harus 403.
4. Login **klien** → diarahkan ke `/klien`; membuka `/absensi` harus 403.
5. `GET /absensi` (sebagai admin/admin absensi) → dasbor menampilkan angka (bukan 0 semua).
6. `GET /absensi/input` → pilih tanggal, simpan satu nilai.
7. `GET /absensi/rapor` → data rapor tampil, tombol Cetak bekerja.
8. `GET /absensi/api?action=anak` → JSON array (harus login admin/admin absensi).
9. `POST /logout` (tombol Keluar) → kembali ke `/login` dan sesi berakhir.

Akun awal dapat dibuat dengan seeder (ganti password sesudahnya):

```powershell
php artisan db:seed --class=RoleAccountSeeder
# admin@pendekar1721.test / Admin#1721        -> /admin
# absensi@pendekar1721.test / Absensi#1721    -> /absensi
# klien@pendekar1721.test / Klien#1721        -> /klien
```

Selebihnya kelola akun lewat panel admin: **Users → Create** → pilih *Jenis User*.`

## 7. Rollback

1. **Kode:** `git checkout main` (atau commit sebelumnya) lalu `npm run build` +
   `php artisan optimize`. Modul bersifat aditif; rute `/absensi` hilang tanpa
   mengganggu rute pendekar1721.
2. **Database:** modul tidak mengubah tabel pendekar1721. Jika perlu, pulihkan
   `semarak_db` dari dump:
   `mysql -u root -p semarak_db < backup-semarak-YYYYMMDD-HHmm.sql`.
3. **Aplikasi native** tetap dapat dijalankan seperti semula (database terpisah),
   karena modul tidak mengubah skema maupun kode aplikasi native.
