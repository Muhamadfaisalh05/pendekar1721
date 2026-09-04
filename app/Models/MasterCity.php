<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $title
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Database\Eloquent\Collection<int, User> $userWorkLocations
 */
class MasterCity extends Model
{
    use HasFactory;

    /**
     * Get the users that have this city as their work location.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<User>
     */
    public function userWorkLocations()
    {
        return $this->belongsToMany(User::class, 'user_work_locations');
    }
}
