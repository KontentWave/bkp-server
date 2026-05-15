<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $landlord_id
 * @property string $inviter_type
 * @property int $inviter_id
 * @property string $phone_number
 * @property string $invited_role
 * @property int|null $escort_external_id
 * @property string|null $escort_ad_url
 * @property Carbon|null $phone_scraped_at
 * @property string $otp_token
 * @property InvitationStatus $status
 * @property Carbon|null $expires_at
 */
class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'landlord_id',
        'inviter_type',
        'inviter_id',
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

    public function inviter(): MorphTo
    {
        return $this->morphTo();
    }
}
