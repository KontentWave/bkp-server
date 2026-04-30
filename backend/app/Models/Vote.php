<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vote extends Model
{
    use HasFactory;

    protected $fillable = [
        'flat_id',
        'escort_id',
        'is_favorite',
    ];

    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
        ];
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Flat::class);
    }

    public function escort(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Escort::class);
    }
}
