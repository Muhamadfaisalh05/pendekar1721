<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string $user_type
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property UserStatus|null $status
 * @property bool $has_current_job
 * @property UserProfile $userProfile
 * @property \Illuminate\Database\Eloquent\Collection<int, MasterSkill> $userSkills
 * @property \Illuminate\Database\Eloquent\Collection<int, MasterTraining> $userTrainings
 * @property \Illuminate\Database\Eloquent\Collection<int, UserExperience> $userExperiences
 * @property \Illuminate\Database\Eloquent\Collection<int, MasterCity> $userWorkLocations
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
        ];
    }

    protected $appends = ['has_current_job'];

    /**
     * Determine if the user can access the given Filament panel.
     *
     * @param Panel $panel The Filament panel instance
     * @return bool True if user can access the panel, false otherwise
     */
    public function canAccessPanel(Panel $panel): bool
    {
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
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<UserProfile>
     */
    public function userProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserProfile::class)->withDefault();
    }

    /**
     * Get the user's skills.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<MasterSkill>
     */
    public function userSkills()
    {
        return $this->belongsToMany(MasterSkill::class, 'user_skills');
    }

    /**
     * Get the user's trainings.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<MasterTraining>
     */
    public function userTrainings()
    {
        return $this->belongsToMany(MasterTraining::class, 'user_trainings');
    }

    /**
     * Get the user's work experiences.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<UserExperience>
     */
    public function userExperiences()
    {
        return $this->hasMany(UserExperience::class);
    }

    /**
     * Get the user's work locations.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<MasterCity>
     */
    public function userWorkLocations()
    {
        return $this->belongsToMany(MasterCity::class, 'user_work_locations');
    }

    /**
     * Get the user (klien/admin) that registered this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this>
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the peserta registered by this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<User, $this>
     */
    public function createdUsers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class, 'created_by');
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
