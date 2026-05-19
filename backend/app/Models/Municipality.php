<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $district
 * @property string $region
 */
class Municipality extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'district',
        'region',
    ];

    /**
     * @return HasMany<Flat, $this>
     */
    public function flats(): HasMany
    {
        return $this->hasMany(Flat::class);
    }
}
