<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Flat */
class FlatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'landlord_id' => $this->landlord_id,
            'title' => $this->title,
            'description' => $this->description,
            'photos' => \App\Http\Resources\FlatPhotoResource::collection($this->whenLoaded('photos')),
            'votes_count' => $this->whenCounted('votes', fn (): int => (int) $this->votes_count),
            'my_vote' => $this->my_vote,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
