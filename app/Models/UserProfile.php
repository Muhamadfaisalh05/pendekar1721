<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $description
 * @property string|null $birth_place
 * @property \Illuminate\Support\Carbon|null $birth_date
 * @property string|null $gender
 * @property string|null $ktp_address
 * @property int|null $ktp_city_id
 * @property string|null $domisili_address
 * @property int|null $domisili_city_id
 * @property int|null $education_degree_id
 * @property int|null $body_weight
 * @property int|null $body_height
 * @property int|null $religion_id
 * @property int|null $ethnic_group_id
 * @property string|null $profile_picture_path
 * @property bool|null $skck_status
 * @property bool|null $surat_kesehatan_status
 * @property string|null $hire_status
 * @property string|null $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $age
 * @property int|null $birth_date_day
 * @property int|null $birth_date_month
 * @property int|null $birth_date_year
 * @property User $user
 * @property MasterCity|null $ktpCity
 * @property MasterCity|null $domisiliCity
 * @property MasterEducationDegree|null $educationDegree
 * @property MasterReligion|null $religion
 * @property MasterEthnicGroup|null $ethnicGroup
 */
class UserProfile extends Model
{
    use HasFactory;

    protected $appends = ['birth_date_day', 'birth_date_month', 'birth_date_year'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'datetime:Y-m-d',
            'skck_status' => 'boolean',
            'surat_kesehatan_status' => 'boolean',
        ];
    }

    /**
     * Get the user that owns this profile.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User>
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the KTP city for this profile.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<MasterCity>
     */
    public function ktpCity()
    {
        return $this->belongsTo(MasterCity::class);
    }

    /**
     * Get the domicile city for this profile.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<MasterCity>
     */
    public function domisiliCity()
    {
        return $this->belongsTo(MasterCity::class);
    }

    /**
     * Get the education degree for this profile.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<MasterEducationDegree>
     */
    public function educationDegree()
    {
        return $this->belongsTo(MasterEducationDegree::class);
    }

    /**
     * Get the religion for this profile.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<MasterReligion>
     */
    public function religion()
    {
        return $this->belongsTo(MasterReligion::class);
    }

    /**
     * Get the ethnic group for this profile.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<MasterEthnicGroup>
     */
    public function ethnicGroup()
    {
        return $this->belongsTo(MasterEthnicGroup::class);
    }

    /**
     * Get the age calculated from birth date.
     *
     * @return Attribute<int, never>
     */
    protected function age(): Attribute
    {
        return Attribute::get(fn () => Carbon::parse($this->birth_date)->age);
    }

    /**
     * Get the day from the birth date.
     *
     * @return Attribute<int|null, never>
     */
    protected function birthDateDay(): Attribute
    {
        if (isset($this->attributes['birth_date']) === false) {
            return Attribute::make(
                get: fn () => null,
            );
        }

        $birthDate = (new Carbon($this->attributes['birth_date']));
        return Attribute::make(
            get: fn () => $birthDate->day,
        );
    }

    /**
     * Get the month from the birth date.
     *
     * @return Attribute<int|null, never>
     */
    protected function birthDateMonth(): Attribute
    {
        if (isset($this->attributes['birth_date']) === false) {
            return Attribute::make(
                get: fn () => null,
            );
        }

        $birthDate = (new Carbon($this->attributes['birth_date']));
        return Attribute::make(
            get: fn () => $birthDate->month,
        );
    }

    /**
     * Get the year from the birth date.
     *
     * @return Attribute<int|null, never>
     */
    protected function birthDateYear(): Attribute
    {
        if (isset($this->attributes['birth_date']) === false) {
            return Attribute::make(
                get: fn () => null,
            );
        }

        $birthDate = (new Carbon($this->attributes['birth_date']));
        return Attribute::make(
            get: fn () => $birthDate->year,
        );
    }
}
