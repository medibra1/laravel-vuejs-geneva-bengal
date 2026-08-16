<?php

namespace App\Models;

use Database\Factories\SiteSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable(['key', 'value'])]
class SiteSetting extends Model implements HasMedia
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasFactory, InteractsWithMedia, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('site_settings')
            ->logOnly(['key', 'value'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * Reads through the full Eloquent model (::first(), not a bare
     * ->value('value') query-builder column pull) so the 'value' => 'array'
     * cast above actually applies — a bare ->value() bypasses casts
     * entirely and returns the raw SQL column, which silently handed
     * callers a JSON-encoded string (e.g. deposit_amount as the string
     * "50000" instead of the int 50000) rather than the decoded value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting === null ? $default : ($setting->value ?? $default);
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Only the row with key='logo' ever actually holds media — attaching
     * media collections to SiteSetting generically (rather than a
     * dedicated SiteLogo model) keeps "the site logo" conceptually a
     * setting like any other, at the cost of this trait being unused on
     * every other row. updateOrCreate() in set() never touches this row's
     * id, so a media attachment survives any future SiteSetting::set('logo', ...)
     * call on the same key.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }

    /**
     * nonQueued(): same reasoning as Cat/Gallery's conversions — an
     * infrequent admin action, and this app's queue only drains
     * periodically via /cron/run (see routes/web.php).
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
}
