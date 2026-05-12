<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'landlord_id',
        'phone_number',
        'invited_role',
        'escort_external_id',
        'escort_ad_url',
        'phone_scraped_at',
        'otp_token',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
            'phone_scraped_at' => 'datetime',
        ];
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(Landlord::class);
    }
}
