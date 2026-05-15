<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $flat_id
 * @property string $storage_disk
 * @property string $storage_path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $byte_size
 * @property int $sort_order
 * @property Flat $flat
 */
class FlatPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'flat_id',
        'storage_disk',
        'storage_path',
        'original_filename',
        'mime_type',
        'byte_size',
        'sort_order',
    ];

    /**
     * @return BelongsTo<Flat, $this>
     */
    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }
}
