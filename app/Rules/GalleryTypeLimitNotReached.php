<?php

namespace App\Rules;

use App\Enums\GalleryType;
use App\Models\Gallery;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Caps how many Gallery rows can exist for types that are consumed by a
 * fixed-size public layout, so an admin can't silently overflow it:
 * HeroSlide backs the homepage hero slider (a handful of slides is the
 * useful range before rotation gets tedious), SocialTile backs the fixed
 * 6-tile homepage grid (see Home.vue's socialTiles). Gallery itself
 * (type=gallery) has no cap — it's a free-form photo grid.
 * Applied on `type` in StoreGalleryRequest only: editing an existing row
 * never changes its type (see UpdateGalleryRequest), so there's nothing to
 * cap on update.
 */
class GalleryTypeLimitNotReached implements ValidationRule
{
    /**
     * @var array<string, int>
     */
    private const LIMITS = [
        'hero_slide' => 5,
        'social_tile' => 6,
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $type = GalleryType::tryFrom((string) $value);

        if ($type === null) {
            return;
        }

        $limit = self::LIMITS[$type->value] ?? null;

        if ($limit === null) {
            return;
        }

        if (Gallery::query()->ofType($type)->count() >= $limit) {
            $fail(__('This section is limited to :limit entries. Delete one before adding another.', ['limit' => $limit]));
        }
    }
}
