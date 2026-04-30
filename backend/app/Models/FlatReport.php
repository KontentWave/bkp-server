<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlatReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'flat_id',
        'reporter_landlord_id',
        'reporter_escort_id',
        'reported_landlord_id',
        'reported_escort_id',
        'reason_code',
    ];

    public function flat(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Flat::class);
    }

    public function reporterLandlord(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Landlord::class, 'reporter_landlord_id');
    }

    public function reporterEscort(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Escort::class, 'reporter_escort_id');
    }

    public function reportedLandlord(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Landlord::class, 'reported_landlord_id');
    }

    public function reportedEscort(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Escort::class, 'reported_escort_id');
    }
}
