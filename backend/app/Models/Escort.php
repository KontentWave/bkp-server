<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property int|null $external_id
 * @property string $phone_number
 * @property string|null $public_key
 */
class Escort extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'external_id',
        'phone_number',
        'public_key',
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function submittedReports(): HasMany
    {
        return $this->hasMany(FlatReport::class, 'reporter_escort_id');
    }

    public function receivedReports(): HasMany
    {
        return $this->hasMany(FlatReport::class, 'reported_escort_id');
    }
}
