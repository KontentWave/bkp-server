<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $flat_id
 * @property string $field_name
 * @property string $language
 * @property string $source_hash
 * @property string $source_text
 * @property string|null $translated_text
 * @property string $status
 * @property string|null $provider
 * @property string|null $failure_message
 * @property \Illuminate\Support\Carbon|null $translated_at
 */
class FlatTranslation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'flat_id',
        'field_name',
        'language',
        'source_hash',
        'source_text',
        'translated_text',
        'status',
        'provider',
        'failure_message',
        'translated_at',
    ];

    protected function casts(): array
    {
        return [
            'translated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Flat, $this>
     */
    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }
}
