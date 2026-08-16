<?php

namespace App\Models;

use App\Enums\CatSex;
use App\Enums\CatType;
use Database\Factories\CatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
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
    use HasFactory, HasSlug, HasStatuses, HasTranslations, InteractsWithMedia, LogsActivity;

    /**
     * @var array<int, string>
     */
    public array $translatable = ['description'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('cats')
            ->logOnly([
                'name', 'type', 'sex', 'color_id', 'second_color_id',
                'price', 'birth_date', 'eye_color', 'available_at', 'diet',
                'litter_trained', 'neutered', 'litter_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

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

    /**
     * @return BelongsTo<Litter, $this>
     */
    public function litter(): BelongsTo
    {
        return $this->belongsTo(Litter::class);
    }

    /**
     * Litters this cat sired — used by the admin Reproducteurs list to
     * surface a breeder's linked litters. Distinct from litter() above,
     * which is the litter *this* cat was born into as a kitten.
     *
     * @return HasMany<Litter, $this>
     */
    public function sireLitters(): HasMany
    {
        return $this->hasMany(Litter::class, 'sire_cat_id');
    }

    /**
     * @return HasMany<Litter, $this>
     */
    public function damLitters(): HasMany
    {
        return $this->hasMany(Litter::class, 'dam_cat_id');
    }

    /**
     * @return HasMany<Deposit, $this>
     */
    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }
}
