<?php

namespace App\Models;

use App\Enums\CatSex;
use App\Enums\CatType;
use Database\Factories\CatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\ModelStatus\HasStatuses;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

/**
 * @property-read string $status Current status name, via HasStatuses' __get() magic (not a real relation/attribute).
 * @property CatType $type Larastan doesn't infer enum casts declared via the casts(): array method syntax.
 * @property CatSex $sex Larastan doesn't infer enum casts declared via the casts(): array method syntax.
 * @property Carbon|null $birth_date
 * @property Carbon|null $available_at
 */
#[Fillable([
    'name', 'type', 'sex', 'color_id', 'second_color_id', 'description',
    'price', 'birth_date', 'eye_color', 'available_at', 'diet',
    'litter_trained', 'neutered', 'litter_id',
])]
class Cat extends Model implements HasMedia
{
    /** @use HasFactory<CatFactory> */
    use HasFactory, HasSlug, HasStatuses, HasTranslations, InteractsWithMedia;

    /**
     * @var array<int, string>
     */
    public array $translatable = ['description'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CatType::class,
            'sex' => CatSex::class,
            'birth_date' => 'date',
            'available_at' => 'date',
            'litter_trained' => 'boolean',
            'neutered' => 'boolean',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    /**
     * @return BelongsTo<Color, $this>
     */
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    /**
     * @return BelongsTo<Color, $this>
     */
    public function secondColor(): BelongsTo
    {
        return $this->belongsTo(Color::class, 'second_color_id');
    }
}
