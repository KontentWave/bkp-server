<?php

namespace App\Models;

use App\Models\FlatReport;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flat extends Model
{
    use HasFactory;

    protected $fillable = [
        'landlord_id',
        'title',
        'description',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Landlord::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(\App\Models\FlatPhoto::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(\App\Models\Vote::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(FlatReport::class);
    }
}
