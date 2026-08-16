<?php

namespace App\Models;

use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

#[Fillable([
    'slug', 'menu_group', 'order', 'title', 'body',
    'meta_title', 'meta_description', 'is_published',
])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasSlug, HasTranslations, LogsActivity;

    /**
     * @var array<int, string>
     */
    public array $translatable = ['title', 'body', 'meta_title', 'meta_description'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pages')
            // body/meta_* excluded — translatable rich-text JSON, too
            // verbose for a diff an admin would actually read; slug/order/
            // is_published are the fields worth auditing here.
            ->logOnly(['slug', 'menu_group', 'order', 'is_published'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(fn (self $page) => $page->getTranslation('title', 'fr'))
            ->saveSlugsTo('slug');
    }
}
