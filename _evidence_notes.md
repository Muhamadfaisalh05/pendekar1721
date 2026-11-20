# _evidence_notes.md — Internal Evidence Summary (DO NOT PUT IN REPORT)
# Gathered from: README.md, CLAUDE.md, routes/, app/Models/, app/Services/,
# app/Modules/Absensi/, database/migrations/, tests/, docker/, docs/, composer.json

---

## 1. Tech Stack (verified in code)

### Language & Runtime
- PHP 8.4.1+ (required; composer.lock and Dockerfile enforce ≥8.4.1)

### Framework & Core Libraries
- Laravel Framework 13.40.0 (composer.lock line 2197–2198)
- Filament 3.2 — dual admin panel (Admin /admin + Client /klien)
- Livewire 3.5 — dynamic UI components
- Laravel Octane 2.5 — optional production performance boost
- Laravel Tinker 3.0 — REPL

### Frontend
- Vite 5.x — asset compilation
- TailwindCSS 3.4 — styling
- Alpine.js (via Filament)
- Blade templating engine
- Inter v4.1 font (self-hosted, no CDN)

### Database
- Primary DB: SQLite (development) / MySQL or PostgreSQL (production)
- Secondary DB: MySQL "semarak" connection — Absensi (SEMARAK) module

### Auth
- Laravel built-in authentication (session-based)
- Role-based access via users.user_type column (admin, admin_absensi, client)
- PHP enum UserStatus (Draft/Published/Archived)
- Filament FilamentUser interface for panel access guard

### Export / Import
- PhpSpreadsheet 5.9 (Excel/CSV import)
- PhpWord 1.4 (Word .docx import/export)

### Tools (verified)
- Git (repository present, .git folder exists)
- Docker (compose.yml, docker/Dockerfile) — Docker image pendekar:1.4
- Cloudflare Tunnel (share-local.ps1 script) — for temporary local sharing
- Laravel Pint (code style) — vendor/bin/pint
- PHPUnit 12 — testing
- Composer 2 — dependency management
- Node.js / NPM 9+ — asset build

### NOT verified (do not claim):
- Postman/Swagger — no .json or openapi spec files found (CONFIRM WITH KHOLIS: was Postman/Swagger used for API documentation?)
- CI/CD pipeline — no GitHub Actions YAML found in repo

---

## 2. User Roles

| user_type        | Dashboard  | Access        |
|------------------|------------|---------------|
| admin            | /admin     | Full admin + absensi |
| admin_absensi    | /absensi   | Absensi module only |
| client           | /klien     | Own profile only |

---

## 3. Database Tables (from migrations + models)

### Primary DB (pendekar1721)
- users — id, name, email, password, user_type, status, created_by, anak_id, graduated_at
- user_profiles — id, user_id, description, birth_place, birth_date, gender, ktp_address, ktp_city_id, domisili_address, domisili_city_id, education_degree_id, body_weight, body_height, religion_id, ethnic_group_id, profile_picture_path, skck_status, surat_kesehatan_status, hire_status, status
- user_experiences — id, user_id, experience_year, experience_description, is_current_job, job_name
- user_skills — user_id, master_skill_id (pivot)
- user_trainings — user_id, master_training_id (pivot)
- user_work_locations — user_id, master_city_id (pivot)
- master_cities — id, title
- master_education_degrees — id, title
- master_skills — id, title, detail
- master_trainings — id, title, detail
- master_religions — id, title
- master_ethnic_groups — id, title
- jobs, cache, sessions (framework tables)

### Secondary DB "semarak" (Absensi module)
- anak — id (string), nama, jenis_kelamin, asal_daerah, agama, umur, angkatan, status
- kegiatan — id, nama
- evaluasi — anak_id, kegiatan_id, tanggal, nilai
- jadwal_kegiatan — kegiatan_id, aturan, hari_json, rentang_json
- masa_pembinaan — angkatan, tanggal_selesai
- catatan_rapor — anak_id, (notes fields)

---

## 4. Modules / Features (verified)

### A. User Profile Management (Admin Panel /admin)
- Full CRUD for users (UserResource.php — 26KB, most complex resource)
- Complete profile: personal data, work experience, skills, training, work location
- Status workflow: Draft → Published → Archived (PHP enum)
- Profile picture upload
- Public slug-based profile directory (/profile/{slug})

### B. Master Data Management (Admin Panel)
- MasterCityResource, MasterEducationDegreeResource, MasterEthnicGroupResource
- MasterReligionResource, MasterSkillResource, MasterTrainingResource

### C. Import Data Module (ImportData.php Filament page)
- Import from Excel (.xlsx/.xls/.csv) and Word (.docx)
- Two modes: MODE_BINAAN (social welfare youth + Draft account) and MODE_KLIEN (direct active account)
- Preview + validation before commit (up to 2000 rows)
- Duplicate detection (name + KTP address + birth date + origin + cohort)
- ImportService.php (88KB — largest service file)
- ImportTemplateService — download template Excel/Word

### D. Graduation / Alumni Module (GraduationService.php)
- Graduate a youth (anak binaan) → activate client account
- Assign random 24-char password (shown once)
- Cross-database transaction compensation on failure
- Linked via users.anak_id foreign key

### E. Absensi Module (SEMARAK integration) (/absensi)
- Migrated existing PHP native "SEMARAK" app into Laravel
- Pages: Dashboard, Input Evaluasi, Data Anak, Summary, Rapor, Pengaturan
- JSON API endpoint: GET/POST /absensi/api?action=... (11 actions)
- API actions READ: dashboard, anak, anak_list, anak_detail, kegiatan, evaluasi, settings, summary, report
- API actions WRITE: save_evaluasi, save_evaluasi_batch, settings_save, report_notes_save, anak_store, anak_update, anak_delete
- Rate limiting: 120 req/min read, 30 req/min write
- 46 fixture oracle tests for byte-perfect API parity

### F. Admin Dashboard (Widgets)
- AdminStatsOverview — user counts by status/role
- BinaanPerAngkatanChart — youth per cohort chart
- KomposisiStatusAkunChart — account status composition chart
- PertumbuhanAkunChart — account growth chart
- AksesCepatWidget — quick access shortcuts
- PerluTindakanWidget — action items (anak without account)

### G. Front-end Public Pages (web.php)
- GET / → HomeController (landing page, program list, alumni directory)
- GET /profile/{slug} → UserProfileController (public profile)
- GET /program/menjahit → SewingProgramController
- GET /program/bimbingan-sosial → SocialGuidanceProgramController
- GET /program/{program} → ProgramDetailController (montir-motor, montir-mobil, tata-boga)
- GET /login, POST /login (throttle:login), POST /logout

### H. Security Implementation (verified in code + tests)
- Multi-layer rate limiting: 5/min per email+IP, 10/min per email, 20/min per IP
- Anti user enumeration (constant response time)
- Session fixation prevention (session()->regenerate())
- Session hijack protection (AuthenticateSession)
- Security headers middleware: X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, Content-Security-Policy
- CSRF protection (web middleware group)
- Self-hosted fonts (no CDN — CSP font-src 'self')
- 13 security feature test files

### I. Testing (verified)
- Feature tests: Absensi/, Auth/, Dashboard/, Front/, Graduation/, Performance/, Security/
- Unit tests: present (tests/Unit/)
- 46 Absensi API oracle fixtures
- Security tests: 13 test files covering login, panel auth, profile privacy, mass assignment, security headers

---

## 5. Chronological Timeline (derived from migration dates + module complexity)

| Period (approx)    | Event                                      |
|--------------------|-------------------------------------------|
| July 2024          | Initial migrations created (commons, users table) |
| Aug 2024           | Added user status, work locations, job_name to experiences |
| May 2025           | user_experiences.is_current_job column added |
| Nov 10–12, 2026    | Major Phase 2 migrations: users.created_by, users.anak_id, nullable email/password, master_trainings/skills detail fields |
| Nov 2026 (ongoing) | Active development, security hardening, Absensi module integration, graduation workflow |

The gap between Aug 2024 and May 2025 and 2026 suggests the project has been evolving across multiple development phases; the internship in Aug–Dec 2026 represents Phase 2/3 (Absensi integration, Graduation, Import, hardening).

NOTE for report: The internship dates are [FILL: exact start and end dates].
Most recent migration date: 2026-11-16 → ongoing near end of internship period.

---

## 6. Organization Facts (from DOCX context, not from code)
- Host org: UPTD Pusat Pelayanan Sosial Griya Bina Remaja (PPSGBR)
- Location: Lembang, West Java
- Under: Dinas Sosial Provinsi Jawa Barat
- Head of Center: Shanti Umbara Dewi, S.IP., M.H.
- On-site supervisor: Bagas Adiantyo Putra, S.Tr.Sos.
- Core function: Social rehabilitation and vocational training for at-risk youth (remaja binaan)
- Programs: sewing (menjahit), motorcycle repair (montir-motor), car repair (montir-mobil), culinary (tata-boga), social guidance (bimbingan sosial)
- [CONFIRM WITH KHOLIS: year UPTD PPSGBR was established, number of staff/clients served, organizational chart details]

---

## 7. Leftovers TO REMOVE from old report
- "Laravel" references unrelated to PENDEKAR1721 → keep only where truly about this project
- "SQL Server" → NOT used; project uses SQLite/MySQL
- "OEE" → NOT in this project
- "MQTT" → NOT in this project
- "Smart Factory" → NOT in this project
- "trolley/melting/casting" → NOT in this project
- "Department 4.0" → NOT in this project
- "PT Pakoakuina" → NOT this organization
- "29 weeks" → internship is 16 weeks
- "UI/UX Analyst" → role is Backend Developer
- "Analyst" (role) → Backend Developer
