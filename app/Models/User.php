<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use App\Modules\Absensi\Models\Anak;
use App\Support\SchemaGuard;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password Boleh NULL untuk binaan yang belum diaktifkan
 * @property string $user_type
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property UserStatus|null $status
 * @property string|null $anak_id Tautan ke `semarak.anak` (FASE 2)
 * @property Carbon|null $graduated_at Penanda resmi alumni (FASE 2)
 * @property bool $has_current_job
 * @property UserProfile $userProfile
 * @property Collection<int, MasterSkill> $userSkills
 * @property Collection<int, MasterTraining> $userTrainings
 * @property Collection<int, UserExperience> $userExperiences
 * @property Collection<int, MasterCity> $userWorkLocations
 */
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    /** Role pada kolom `user_type`. */
    public const ROLE_ADMIN = 'admin';

    public const ROLE_ADMIN_ABSENSI = 'admin_absensi';

    public const ROLE_CLIENT = 'client';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'user_type',
        'status',
        'created_by',
        'anak_id',
        'graduated_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'graduated_at' => 'datetime',
        ];
    }

    protected $appends = ['has_current_job'];

    /**
     * Determine if the user can access the given Filament panel.
     *
     * Status akun diperiksa lebih dulu: akun `Draft` (belum diaktifkan) dan
     * `Archived` (diarsipkan) tidak boleh membuka panel mana pun, termasuk
     * bila `user_type`-nya cocok. Status `null` (data lama) tetap diizinkan
     * agar akun yang belum pernah diisi statusnya tidak tiba-tiba terkunci.
     *
     * @param  Panel  $panel  The Filament panel instance
     * @return bool True if user can access the panel, false otherwise
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->status === UserStatus::Draft || $this->status === UserStatus::Archived) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->isAdmin(),
            'client' => $this->isClient(),
            default => false,
        };
    }

    public function isAdmin(): bool
    {
        return $this->user_type === self::ROLE_ADMIN;
    }

    public function isAdminAbsensi(): bool
    {
        return $this->user_type === self::ROLE_ADMIN_ABSENSI;
    }

    public function isClient(): bool
    {
        return $this->user_type === self::ROLE_CLIENT;
    }

    /**
     * Scope "alumni" — definisi resmi yang disetujui pemilik produk (12 Nov 2026):
     *
     *     user_type = 'client' AND status = 'published' AND graduated_at IS NOT NULL
     *
     * Klien lama yang belum punya `graduated_at` — termasuk klien yang
     * `created_by`-nya NULL — TIDAK dihitung alumni. `created_by IS NULL`
     * sengaja tidak dipakai sebagai penanda karena klien baru pun bisa
     * bernilai NULL. Backfill `graduated_at` dijalankan terpisah setelah
     * audit produksi selesai (lihat docs/AUDIT-PRODUKSI-USERS.md).
     *
     * PENGAMAN SKEMA: bila kolom `graduated_at` belum ada (migrasi Fase 2
     * tertunda), scope mengembalikan hasil kosong (`1 = 0`) alih-alih error
     * SQL. Dashboard klien otomatis memakai fallback "Angka sementara".
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeAlumni(Builder $query): Builder
    {
        if (! SchemaGuard::usersPunyaTanggalLulus()) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('user_type', self::ROLE_CLIENT)
            ->where('status', UserStatus::Published->value)
            ->whereNotNull('graduated_at');
    }

    /**
     * Apakah akun ini alumni menurut definisi resmi di {@see scopeAlumni()}.
     */
    public function isAlumni(): bool
    {
        return $this->user_type === self::ROLE_CLIENT
            && $this->status === UserStatus::Published
            && $this->graduated_at !== null;
    }

    /**
     * Admin pendekar (superuser) dan admin absensi boleh membuka modul absensi.
     */
    public function canAccessAbsensi(): bool
    {
        return $this->isAdmin() || $this->isAdminAbsensi();
    }

    /**
     * Dashboard tujuan setelah login, ditentukan oleh role.
     */
    public function dashboardUrl(): string
    {
        return match ($this->user_type) {
            self::ROLE_ADMIN => '/admin',
            self::ROLE_ADMIN_ABSENSI => '/absensi',
            self::ROLE_CLIENT => '/klien',
            default => '/',
        };
    }

    /**
     * Label role untuk ditampilkan di UI.
     */
    public function roleLabel(): string
    {
        return match ($this->user_type) {
            self::ROLE_ADMIN => 'Administrator Pendekar',
            self::ROLE_ADMIN_ABSENSI => 'Admin Absensi (SEMARAK)',
            self::ROLE_CLIENT => 'Klien',
            default => 'Tanpa Role',
        };
    }

    /**
     * Get the user's profile.
     *
     * @return HasOne<UserProfile>
     */
    public function userProfile(): HasOne
    {
        return $this->hasOne(UserProfile::class)->withDefault();
    }

    /**
     * Get the user's skills.
     *
     * @return BelongsToMany<MasterSkill>
     */
    public function userSkills()
    {
        return $this->belongsToMany(MasterSkill::class, 'user_skills');
    }

    /**
     * Get the user's trainings.
     *
     * @return BelongsToMany<MasterTraining>
     */
    public function userTrainings()
    {
        return $this->belongsToMany(MasterTraining::class, 'user_trainings');
    }

    /**
     * Get the user's work experiences.
     *
     * @return HasMany<UserExperience>
     */
    public function userExperiences()
    {
        return $this->hasMany(UserExperience::class);
    }

    /**
     * Get the user's work locations.
     *
     * @return BelongsToMany<MasterCity>
     */
    public function userWorkLocations()
    {
        return $this->belongsToMany(MasterCity::class, 'user_work_locations');
    }

    /**
     * Get the user (klien/admin) that registered this user.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the peserta registered by this user.
     *
     * @return HasMany<User, $this>
     */
    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    /**
     * Baris `semarak.anak` yang tertaut ke akun ini (FASE 2).
     *
     * Relasi lintas database: `anak` berada di koneksi `semarak`, jadi tidak
     * ada foreign key — integritas dijaga oleh ImportBinaanService dan
     * GraduationService.
     *
     * @return BelongsTo<Anak, $this>
     */
    public function anak(): BelongsTo
    {
        return $this->belongsTo(Anak::class, 'anak_id', 'id');
    }

    /**
     * Check if the user has a current job.
     *
     * @return bool True if user has a current job, false otherwise
     */
    public function getHasCurrentJobAttribute(): bool
    {
        return $this->userExperiences()
            ->where('is_current_job', true)
            ->exists();
    }
}
