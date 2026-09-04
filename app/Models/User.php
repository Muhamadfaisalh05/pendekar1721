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

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
        if ($panel->getId() === 'admin' && $this->user_type === 'admin') {
            return true;
        }

        if ($panel->getId() === 'client' && $this->user_type === 'client') {
            return true;
        }

        return false;
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
