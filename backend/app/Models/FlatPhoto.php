<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function flat(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Flat::class);
    }
}
