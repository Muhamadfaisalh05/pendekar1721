# PENDEKAR1721

![Laravel](https://img.shields.io/badge/Laravel-11.9-FF2D20?style=for-the-badge&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php)
![Filament](https://img.shields.io/badge/Filament-3.2-EAB308?style=for-the-badge&logo=filament)

Sistem Manajemen Profil Pengguna dan Data Master dengan Laravel 11 dan Filament 3

---

## 📋 Tentang Proyek

**PENDEKAR1721** adalah aplikasi manajemen profil pengguna yang komprehensif dengan sistem data master terintegrasi. Aplikasi ini menggunakan framework Laravel 11 combined dengan Filament 3 untuk menyediakan antarmuka admin yang modern dan user-friendly.

Aplikasi ini dirancang untuk mengelola:
- Profil pengguna dengan informasi personal, pengalaman kerja, keahlian, dan pelatihan
- Data master referensi (kota, pendidikan, agama, etnis, keahlian, pelatihan)
- Direktori profil publik yang dapat diakses melalui URL berbasis slug
- Sistem alur kerja status berbasis enum PHP 8.2

## ✨ Fitur Utama

- **🔐 Dual Panel System** - Panel Admin (`/admin`) dan Client (`/klien`) dengan tingkat akses berbeda
- **👥 Manajemen Pengguna** - Profil lengkap termasuk info personal, pengalaman kerja, keahlian, dan pelatihan
- **📊 Master Data Management** - Kelola data referensi: kota, agama, etnis, pendidikan, keahlian, pelatihan
- **🌐 Profil Publik** - Direktori profil yang dapat diakses publik dengan URL berbasis slug
- **🔄 Status Workflow** - Sistem status pengguna menggunakan PHP 8.2 enums (Draft/Published/Archived)
- **⚡ Modern Tech Stack** - Laravel 11, Filament 3, Livewire 3, TailwindCSS, Vite

## 🛠️ Teknologi

### Backend
- **PHP 8.2+** - Bahasa pemrograman utama
- **Laravel 11.9** - Framework PHP
- **Filament 3.2** - Admin panel framework
- **Livewire 3.5** - Dynamic UI components
- **Laravel Octane 2.5** - Performance optimization

### Frontend
- **Vite 5.0** - Asset compilation
- **TailwindCSS 3.4** - CSS framework
- **Alpine.js** - Interactive JavaScript
- **Blade Templating** - Template engine Laravel

### Database
- **SQLite** (Development)
- **MySQL/PostgreSQL** (Production)

## 📦 Persyaratan Sistem

Sebelum memulai, pastikan sistem Anda memenuhi persyaratan berikut:

- **PHP >= 8.2**
- **Composer >= 2.0**
- **Node.js >= 18.0**
- **NPM >= 9.0**
- **SQLite / MySQL / PostgreSQL**
- **Web Server** (Apache/Nginx) untuk production

## 🚀 Instalasi

Ikuti langkah-langkah berikut untuk menginstall aplikasi di environment lokal:

### 1. Clone Repository

```bash
git clone <repository-url>
cd pendekar1721
```

### 2. Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install NPM dependencies
npm install
```

### 3. Setup Environment

```bash
# Copy file environment example
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4. Setup Database

```bash
# Buat file database SQLite untuk development
touch database/database.sqlite

# Jalankan migration dan seeder
php artisan migrate --seed
```

### 5. Link Storage

```bash
# Link storage untuk file uploads
php artisan storage:link
```

### 6. Build Assets

```bash
# Build assets untuk development
npm run build
```

### 7. Start Development Server

```bash
# Start development server
php artisan serve

# Start Vite development server (terminal terpisah)
npm run dev
```

Aplikasi sekarang dapat diakses di `http://localhost:8000`

## ⚙️ Konfigurasi

File `.env` berisi konfigurasi penting untuk aplikasi. Berikut adalah variabel utama yang perlu diperhatikan:

### Basic Configuration

```env
APP_NAME=PENDEKAR1721
APP_ENV=local                    # local / production
APP_DEBUG=true                   # true / false
APP_URL=http://localhost:8000
APP_LOCALE=id                    # Locale bahasa Indonesia
```

### Database Configuration

**Untuk SQLite (Development):**
```env
DB_CONNECTION=sqlite
```

**Untuk MySQL:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pendekar1721
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

**Untuk PostgreSQL:**
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pendekar1721
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

## 💾 Database

Aplikasi menggunakan struktur database yang terorganisir dengan baik:

### Tabel Utama
- **users** - Data pengguna dengan autentikasi
- **user_profiles** - Profil lengkap pengguna
- **user_experiences** - Pengalaman kerja pengguna
- **user_skills** - Keahlian pengguna (pivot table)
- **user_trainings** - Pelatihan pengguna (pivot table)
- **user_work_locations** - Lokasi kerja pengguna (pivot table)

### Tabel Master
- **master_cities** - Data kota/kabupaten
- **master_education_degrees** - Tingkat pendidikan
- **master_ethnic_groups** - Data suku/etnis
- **master_religions** - Data agama
- **master_skills** - Data keahlian
- **master_trainings** - Data pelatihan

### Seeder Data

Database seeder menyediakan data awal:
- **Admin User**: `test@example.com` (user_type: admin)
- **Master Religions**: Islam, Kristen, Katholik, Hindu, Budha
- **Master Ethnic Groups**: Sunda, Betawi, Jawa
- **Master Cities**: Kota Bandung
- **Master Education**: SMP, SMA, MA, MAN
- **Master Skills**: Memasak, Memperbaiki Perangkat Elektronik
- **Master Training**: Pelatihan Reparasi, Pelatihan Memasak

## 👥 Akun Default

Setelah menjalankan `php artisan migrate --seed`, akun default berikut akan dibuat:

### Admin User
- **Email**: `test@example.com`
- **User Type**: `admin`
- **Password**: (lihat database seeder)
- **Akses**: Panel Admin (`/admin`)

⚠️ **PENTING**: Ubah password default di environment production!

## 🎨 Filament Admin Panels

Aplikasi ini memiliki dua panel Filament terpisah dengan fungsi berbeda:

### Admin Panel (`/admin`)
- **Akses**: Pengguna dengan `user_type = 'admin'`
- **Tema Warna**: Amber (Kuning Emas)
- **Fitur**:
  - Manajemen pengguna lengkap
  - Manajemen data master (kota, agama, etnis, dll)
  - Manajemen profil dan pengalaman kerja
  - Monitoring dan reporting

### Client Panel (`/klien`)
- **Akses**: Pengguna dengan `user_type = 'client'`
- **Tema Warna**: Green (Hijau)
- **Fitur**:
  - Manajemen profil sendiri
  - Update pengalaman kerja dan keahlian
  - Resource spesifik untuk client

## 🧪 Pengujian

Jalankan test suite untuk memastikan aplikasi berjalan dengan baik:

```bash
# Jalankan semua test
php artisan test

# Jalankan PHPUnit
vendor/bin/phpunit

# Jalankan test spesifik
php artisan test --filter UserTest

# Jalankan test dengan coverage
php artisan test --coverage
```

## 📝 Struktur Direktori

Berikut adalah struktur direktori utama aplikasi:

```
app/
├── Enums/                    # PHP 8.2 backed enums
│   └── UserStatus.php       # Enum status user
├── Filament/                 # Admin panel resources
│   ├── Resources/           # Admin panel resources
│   │   ├── UserResource.php
│   │   ├── MasterCityResource.php
│   │   └── ...
│   ├── Client/              # Client panel resources
│   └── Pages/               # Custom Filament pages
├── Http/Controllers/
│   └── Front/               # Front-facing controllers
│       ├── HomeController.php
│       └── UserProfileController.php
├── Models/                  # Eloquent models
│   ├── User.php
│   ├── UserProfile.php
│   └── ...
└── Providers/Filament/      # Panel providers
    ├── AdminPanelProvider.php
    └── ClientPanelProvider.php

database/
├── migrations/              # Database migrations
└── seeders/                # Database seeders

resources/views/
├── components/              # Blade components
├── front/                   # Front-facing templates
└── layouts/                 # Layout templates
```

## 🔧 Perintah Artisan Berguna

Berikut adalah perintah Artisan yang sering digunakan:

### Development

```bash
# Start development server
php artisan serve

# Start Vite dev server
npm run dev

# Reset dan seed database
php artisan migrate:fresh --seed

# Process background jobs
php artisan queue:work
```

### Cache Management

```bash
# Clear semua cache
php artisan cache:clear

# Clear config cache
php artisan config:clear

# Clear route cache
php artisan route:clear

# Clear view cache
php artisan view:clear
```

### Production

```bash
# Build assets untuk production
npm run build

# Cache config untuk performance
php artisan config:cache

# Cache routes untuk performance
php artisan route:cache

# Cache views untuk performance
php artisan view:cache
```

### Database

```bash
# Jalankan migration
php artisan migrate

# Rollback migration
php artisan migrate:rollback

# Reset database
php artisan migrate:fresh

# Seed database
php artisan db:seed
```

## 🤝 Kontribusi

Kontribusi dalam bentuk pull request, issue report, atau saran sangat dihargai. Silakan ikuti langkah berikut:

1. Fork repository ini
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

## 📄 Lisensi

Project ini dilisensikan under MIT License - lihat file LICENSE untuk details.

## 📞 Support

Jika Anda mengalami masalah atau memiliki pertanyaan:

- Buka issue di repository
- Contact development team
- Lihat dokumentasi Laravel: https://laravel.com/docs
- Lihat dokumentasi Filament: https://filamentphp.com/docs

---

**Dibuat dengan ❤️ menggunakan Laravel 11 dan Filament 3**
