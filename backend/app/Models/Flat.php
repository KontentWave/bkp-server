<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $landlord_id
 * @property int|null $municipality_id
 * @property string $title
 * @property string|null $description
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property string|null $whatsapp_url
 * @property string|null $telegram_url
 * @property string|null $viber_url
 * @property \App\Models\Municipality|null $municipality
 * @property int|null $votes_count
 * @property bool|null $my_vote
 * @property int|null $landlord_reports_count
 * @property array<int, string> $landlord_report_reasons
 * @property string|null $my_landlord_report_reason
 * @property Collection<int, FlatPhoto> $photos
 * @property Collection<int, FlatReport> $reports
 */
class Flat extends Model
{
    use HasFactory;

    protected $fillable = [
        'landlord_id',
        'municipality_id',
        'title',
        'description',
        'contact_phone',
        'contact_email',
        'whatsapp_url',
        'telegram_url',
        'viber_url',
    ];

    /**
     * @return BelongsTo<Landlord, $this>
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(Landlord::class);
    }

    /**
     * @return BelongsTo<\App\Models\Municipality, $this>
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Municipality::class);
    }

    /**
     * @return HasMany<FlatPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(FlatPhoto::class);
    }

    /**
     * @return HasMany<Vote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * @return HasMany<FlatReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(FlatReport::class);
    }
}
