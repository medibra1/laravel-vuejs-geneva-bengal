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
            'type' => $gallery->type->value,
            'image_url' => $gallery->getFirstMediaUrl('image') ?: null,
            'image_sm_url' => $gallery->getFirstMediaUrl('image', 'sm') ?: null,
            'image_md_url' => $gallery->getFirstMediaUrl('image', 'md') ?: null,
            'image_lg_url' => $gallery->getFirstMediaUrl('image', 'lg') ?: null,
        ];
    }
}
