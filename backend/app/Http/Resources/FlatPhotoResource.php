<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
/** @mixin \App\Models\FlatPhoto */
class FlatPhotoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'flat_id' => $this->flat_id,
            'content_url' => '/api/photos/'.$this->id.'/content',
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'byte_size' => $this->byte_size,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
