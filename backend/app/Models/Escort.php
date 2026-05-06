<?php

namespace App\Models;

use App\Models\FlatReport;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

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
        return $this->hasMany(\App\Models\Vote::class);
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
