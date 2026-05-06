<?php

namespace App\Models;

use App\Models\FlatReport;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Landlord extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'phone_number',
        'public_key',
        'is_verified',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
        ];
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function flats(): HasMany
    {
        return $this->hasMany(\App\Models\Flat::class);
    }

    public function submittedReports(): HasMany
    {
        return $this->hasMany(FlatReport::class, 'reporter_landlord_id');
    }

    public function receivedReports(): HasMany
    {
        return $this->hasMany(FlatReport::class, 'reported_landlord_id');
    }
}
