<?php

namespace App\Models;

use App\Enums\GalleryType;
use Database\Factories\GalleryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property GalleryType $type Larastan doesn't infer enum casts declared via the casts(): array method syntax.
 */
#[Fillable(['caption', 'position', 'type'])]
class Gallery extends Model implements HasMedia
{
    /** @use HasFactory<GalleryFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => GalleryType::class,
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    /**
     * @param  Builder<Gallery>  $query
     * @return Builder<Gallery>
     */
    public function scopeOfType(Builder $query, GalleryType $type): Builder
    {
        return $query->where('type', $type);
    }
}
