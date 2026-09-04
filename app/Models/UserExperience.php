<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $experience_year
 * @property string|null $experience_description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $experience_job_name
 * @property int|null $sort_order
 * @property bool $is_current_job
 * @property User $user
 */
class UserExperience extends Model
{
    use HasFactory;

    /**
     * Get the user that owns this experience.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User>
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
