<?php

namespace App\Models;

use Database\Factories\OwnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['first_name', 'last_name', 'email', 'phone', 'city', 'desired_cat_id', 'desired_color_id'])]
class Owner extends Model
{
    /** @use HasFactory<OwnerFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('owners')
            ->logOnly(['first_name', 'last_name', 'email', 'phone', 'city', 'desired_cat_id', 'desired_color_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Cat, $this>
     */
    public function desiredCat(): BelongsTo
    {
        return $this->belongsTo(Cat::class, 'desired_cat_id');
    }

    /**
     * @return BelongsTo<Color, $this>
     */
    public function desiredColor(): BelongsTo
    {
        return $this->belongsTo(Color::class, 'desired_color_id');
    }

    /**
     * @return HasMany<Deposit, $this>
     */
    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }
}
