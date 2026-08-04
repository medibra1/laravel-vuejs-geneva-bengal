<?php

namespace App\Models;

use App\Enums\DepositStatus;
use Database\Factories\DepositFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property DepositStatus $status Larastan doesn't infer enum casts declared via the casts(): array method syntax.
 * @property Carbon|null $paid_at
 */
#[Fillable(['cat_id', 'name', 'email', 'phone', 'amount', 'currency', 'status', 'provider', 'provider_reference', 'paid_at'])]
class Deposit extends Model
{
    /** @use HasFactory<DepositFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => DepositStatus::class,
            'paid_at' => 'datetime',
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
