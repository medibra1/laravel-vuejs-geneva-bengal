<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $gallery = $this->resource;

        return [
            'id' => $gallery->id,
            'caption' => $gallery->caption,
            'position' => $gallery->position,
            'image_url' => $gallery->getFirstMediaUrl('image') ?: null,
        ];
    }
}
