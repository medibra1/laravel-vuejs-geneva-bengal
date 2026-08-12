<?php

namespace App\Models;

use Database\Factories\FaqItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

#[Fillable(['question', 'answer', 'order', 'slug'])]
class FaqItem extends Model
{
    /** @use HasFactory<FaqItemFactory> */
    use HasFactory, HasSlug, HasTranslations;

    /**
     * @var array<int, string>
     */
    public array $translatable = ['question', 'answer'];

    /**
     * Same pattern as Page::getSlugOptions() — a closure (not the
     * 'question.fr' string form) since `question` is stored as translatable
     * JSON, not a plain column generateSlugsFrom() could read directly.
     * Backs the unique `slug` column (see the faq_items migration) that
     * Admin\FaqItemController's store()/update() would otherwise never
     * populate on their own.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(fn (self $item) => $item->getTranslation('question', 'fr'))
            ->saveSlugsTo('slug');
    }
}
