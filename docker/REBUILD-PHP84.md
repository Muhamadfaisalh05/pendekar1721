# Rebuild Image Docker ke PHP 8.4.1+

**Alasan wajib:** `composer.lock` memakai `symfony/* 8.1.x` → `requires php (>=8.4.1)`.
Jika image masih PHP 8.3, `composer install` akan gagal saat deployment:

```
- symfony/http-foundation v8.1.11 requires php (>=8.4.1) -> your php version (8.3.x) does not satisfy that requirement.
```

Mulai sekarang `composer.json` memakai `config.platform-check = true`, jadi kegagalan versi PHP
terdeteksi **lebih awal** (bukan saat runtime).

## 1. Build image baru (di mesin yang punya Docker)

```bash
# dari root repo pendekar1721
docker build -f docker/Dockerfile -t 192.168.200.110:8080/pendekar:1.5 .
```

Tag `1.5` dipakai agar `1.4` (PHP 8.3 lama) tetap tersedia untuk rollback.

## 2. Uji image sebelum push

```bash
docker run --rm 192.168.200.110:8080/pendekar:1.5 php -v
# harus menampilkan PHP 8.4.x dengan x >= 1  (mis. PHP 8.4.11)

docker run --rm -w /var/www/html 192.168.200.110:8080/pendekar:1.5 composer check-platform-reqs
# semua baris harus "success"

docker run --rm -w /var/www/html 192.168.200.110:8080/pendekar:1.5 php artisan --version
# Laravel Framework 13.40.0
```

Smoke test dengan env produksi:

```bash
docker run --rm -p 8071:8000 --env-file production.env \
  -v "$PWD/database/database.sqlite:/var/www/html/database/database.sqlite" \
  -v "$PWD/storage_app:/var/www/html/storage/app" \
  192.168.200.110:8080/pendekar:1.5
# lalu buka http://localhost:8071/ dan /admin/login (harus 200)
```

## 3. Push ke registry

```bash
docker push 192.168.200.110:8080/pendekar:1.5
```

## 4. Ganti tag di `compose.yml`

```yaml
services:
  pendekar_php:
    image: 192.168.200.110:8080/pendekar:1.5   # sebelumnya :1.4
```

## 5. Deploy & verifikasi di server

```bash
docker compose pull pendekar_php
docker compose up -d pendekar_php

docker compose exec pendekar_php php -v                        # >= 8.4.1
docker compose exec pendekar_php php artisan --version          # 13.40.0
docker compose exec pendekar_php php artisan optimize:clear
docker compose exec pendekar_php php artisan octane:reload      # bila memakai Octane
docker compose logs --tail=50 pendekar_php
```

Cek manual: halaman publik `/`, `/admin/login`, `/klien/login` harus 200 dan user lama **tetap login**
(config sesi tidak diubah, cookie tetap `laravel_session`).

## 6. Rollback

```bash
docker compose down pendekar_php
# kembalikan image ke :1.4 lalu
docker compose up -d pendekar_php
```

## Catatan penting

- **Fail-fast runtime**: dengan `config.platform-check = true`, Composer menghasilkan
  `vendor/composer/platform_check.php` yang memeriksa versi PHP **dan** ekstensi wajib
  (`dom`, `fileinfo`, `filter`, `gd`, `hash`, `iconv`, `intl`, `mbstring`, `openssl`,
  `pcre`, `session`, `tokenizer`, `xml`, `xmlreader`, `xmlwriter`, `zip`, `zlib`).
  Bila server belum memasang salah satunya, aplikasi langsung menolak boot dengan pesan jelas
  (lebih baik daripada error 500 saat fitur export Excel/Word dipakai).
  `docker/Dockerfile` sudah memasang semuanya.
- `.dockerignore` meng-exclude `vendor/`, `.env`, `database/database.sqlite`, dan `storage/`
  sehingga image bersih; dependency dipasang ulang lewat `composer install --no-dev` di dalam image.
- Kredensial tetap berasal dari `production.env` (`env_file` di `compose.yml`), **tidak** di-bake ke image.
- `CMD` default memakai `php artisan serve`. Jika produksi memakai Octane, override lewat `command:`
  (lihat komentar di akhir `docker/Dockerfile`) — butuh ekstensi `swoole` atau binary `rr` di image.
- Repo **tidak** menyimpan `Dockerfile` sebelumnya (image lama dibangun di luar repo). File
  `docker/Dockerfile` ini adalah sumber build baru yang harus dijaga konsisten dengan `composer.lock`.
