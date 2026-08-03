<?php

namespace App\Models;

use Database\Factories\LitterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sire_cat_id', 'dam_cat_id', 'expected_date', 'notes'])]
class Litter extends Model
{
    /** @use HasFactory<LitterFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expected_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Cat, $this>
     */
    public function sire(): BelongsTo
    {
        return $this->belongsTo(Cat::class, 'sire_cat_id');
    }

    /**
     * @return BelongsTo<Cat, $this>
     */
    public function dam(): BelongsTo
    {
        return $this->belongsTo(Cat::class, 'dam_cat_id');
    }

    /**
     * @return HasMany<Cat, $this>
     */
    public function kittens(): HasMany
    {
        return $this->hasMany(Cat::class);
    }
}
