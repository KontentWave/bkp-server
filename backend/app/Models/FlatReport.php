<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $flat_id
 * @property int|null $reporter_landlord_id
 * @property int|null $reporter_escort_id
 * @property int|null $reported_landlord_id
 * @property int|null $reported_escort_id
 * @property int|null $reported_escort_external_id
 * @property string $reason_code
 * @property Carbon|null $updated_at
 * @property Flat|null $flat
 * @property Escort|null $reportedEscort
 */
class FlatReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'flat_id',
        'reporter_landlord_id',
        'reporter_escort_id',
        'reported_landlord_id',
        'reported_escort_id',
        'reported_escort_external_id',
        'reason_code',
    ];

    /**
     * @return BelongsTo<Flat, $this>
     */
    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    /**
     * @return BelongsTo<Landlord, $this>
     */
    public function reporterLandlord(): BelongsTo
    {
        return $this->belongsTo(Landlord::class, 'reporter_landlord_id');
    }

    /**
     * @return BelongsTo<Escort, $this>
     */
    public function reporterEscort(): BelongsTo
    {
        return $this->belongsTo(Escort::class, 'reporter_escort_id');
    }

    /**
     * @return BelongsTo<Landlord, $this>
     */
    public function reportedLandlord(): BelongsTo
    {
        return $this->belongsTo(Landlord::class, 'reported_landlord_id');
    }

    /**
     * @return BelongsTo<Escort, $this>
     */
    public function reportedEscort(): BelongsTo
    {
        return $this->belongsTo(Escort::class, 'reported_escort_id');
    }
}
