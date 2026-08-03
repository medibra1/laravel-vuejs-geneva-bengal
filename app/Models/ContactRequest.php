<?php

namespace App\Models;

use App\Enums\ContactReason;
use App\Enums\ContactStatus;
use Database\Factories\ContactRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ContactReason $reason Larastan doesn't infer enum casts declared via the casts(): array method syntax.
 * @property ContactStatus $status
 */
#[Fillable(['name', 'email', 'reason', 'cat_id', 'city', 'message', 'status'])]
class ContactRequest extends Model
{
    /** @use HasFactory<ContactRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reason' => ContactReason::class,
            'status' => ContactStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Cat, $this>
     */
    public function cat(): BelongsTo
    {
        return $this->belongsTo(Cat::class);
    }
}
