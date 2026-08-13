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
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
     * nonQueued(): uploads are an infrequent admin action, and this app's
     * queue only drains periodically via the /cron/run endpoint (see
     * routes/web.php) — conversions must be ready immediately, not wait on
     * that cycle.
     *
     * nonQueued() is called first, before the width()/format()/quality()
     * chain: Manipulations declares `@mixin ImageDriver`, so Larastan
     * resolves those magic-__call methods against ImageDriver rather than
     * Conversion — chaining nonQueued() (a real method on Conversion) after
     * them makes it look undefined. Calling it first keeps the chain typed
     * as Conversion throughout; execution order has no actual effect here.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        foreach (['sm' => 480, 'md' => 800, 'lg' => 1400] as $name => $width) {
            $this->addMediaConversion($name)
                ->nonQueued()
                ->width($width)
                ->format('webp')
                ->quality(80);
        }
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
