<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $flat_id
 * @property int $escort_id
 * @property bool $is_favorite
 */
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

    /**
     * @return BelongsTo<Flat, $this>
     */
    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    /**
     * @return BelongsTo<Escort, $this>
     */
    public function escort(): BelongsTo
    {
        return $this->belongsTo(Escort::class);
    }
}
