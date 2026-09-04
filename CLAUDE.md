# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Application Overview

PENDEKAR1721 is a Laravel 11 application for managing user profiles and professional data. The system features:

- **Dual-panel Filament admin interface** with separate Admin and Client panels
- **User management** with comprehensive profiles, work experiences, skills, and training records
- **Master data management** for reference data (cities, education degrees, ethnic groups, religions, skills, training)
- **Public-facing profiles** accessible via slug-based URLs
- **Status-based workflow** using PHP 8.2 enums for user status management

## Development Commands

### Frontend Development
```bash
npm run dev          # Start Vite development server
npm run build        # Build assets for production
```

### Backend Development
```bash
php artisan serve                    # Start local development server
php artisan migrate:fresh --seed     # Fresh migration with seeding
php artisan queue:work               # Process background jobs
php artisan cache:clear              # Clear application cache
php artisan config:clear             # Clear configuration cache
```

### Testing
```bash
php artisan test                     # Run all tests
php artisan test --filter UserTest   # Run specific test class
vendor/bin/phpunit                   # Alternative test runner
```

### Code Quality
```bash
./vendor/bin/pint                    # Run Laravel Pint (code style fixer)
```

## Architecture

### Dual Panel System
The application uses **two separate Filament panels** with distinct access control:

- **Admin Panel** (`/admin`) - For administrative users with `user_type = 'admin'`
- **Client Panel** (`/klien`) - For client users with `user_type = 'client'`

Panel access is controlled via `User::canAccessPanel()` method in `app/Models/User.php`.

### Domain Model Structure

**Core User Relationships:**
- `User` → `UserProfile` (one-to-one)
- `User` → `UserExperience[]` (one-to-many)
- `User` ↔ `MasterSkill` (many-to-many via `user_skills`)
- `User` ↔ `MasterTraining` (many-to-many via `user_trainings`)
- `User` ↔ `MasterCity` (many-to-many via `user_work_locations`)

**Master Data Tables:** All prefixed with `master_` and managed via Filament resources:
- `master_cities` - Geographic locations
- `master_education_degrees` - Education levels
- `master_ethnic_groups` - Ethnic classifications
- `master_religions` - Religious affiliations
- `master_skills` - Professional skills
- `master_trainings` - Training programs

### Enum-Based Status System
User status uses PHP 8.2 backed enums (`app/Enums/UserStatus.php`):
- `Draft` - "Tidak Aktif (Draft)" (Inactive)
- `Published` - "Aktif" (Active)
- `Archived` - "Diarsipkan" (Archived)

Enums implement Filament's `HasLabel` interface for localized display.

### Key Application Patterns

**Model Unguarding:** `Model::unguard()` is enabled in `AppServiceProvider` - mass assignment protection is disabled globally.

**Comprehensive PHPDoc:** Models include detailed `@property` annotations for IDE support and type safety.

**Slug-based Routing:** Public profiles use slugs instead of IDs (see `routes/web.php`).

**HasCurrentJob Attribute:** Users have a computed attribute `has_current_job` that checks `user_experiences` for `is_current_job = true`.

### File Organization

```
app/
├── Enums/              # PHP 8.2 backed enums (UserStatus, etc.)
├── Filament/
│   ├── Resources/      # Admin panel resources (UserResource, Master*Resources)
│   ├── Client/         # Client-specific resources (future use)
│   └── Pages/          # Custom Filament pages
├── Http/Controllers/
│   └── Front/          # Front-facing controllers (HomeController, UserProfileController)
├── Models/             # Eloquent models with comprehensive PHPDoc
└── Providers/
    └── Filament/       # Panel providers (AdminPanelProvider, ClientPanelProvider)

resources/views/
├── components/         # Blade components
├── front/              # Front-facing page templates
├── layouts/            # Blade layout templates
└── filament/           # Filament customizations
```

## Configuration Notes

- **URL/Scheme Forcing:** App can force root URL and scheme via `config/app.php` (`force_root_url`, `force_scheme`)
- **Dark Mode:** Disabled for both Filament panels
- **Max Content Width:** Set to `full` for both panels
- **Primary Colors:** Admin uses Amber, Client uses Green

## Database Considerations

- The `commons` migration (`2024_07_22_033006_create_commons_table.php`) creates multiple master tables in a single migration
- User work locations and experiences support pivot table relationships
- Experience records include `is_current_job` boolean for tracking current employment

## Frontend Stack

- **Vite** for asset compilation
- **TailwindCSS** for styling
- **Livewire 3** for dynamic components
- **Alpine.js** (loaded via Filament)
- **Blade** templating with component-based architecture

Entry points: `resources/sass/app.scss` and `resources/js/app.js`
