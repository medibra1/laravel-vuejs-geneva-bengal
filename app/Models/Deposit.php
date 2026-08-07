<?php

namespace App\Models;

use App\Enums\DepositStatus;
use App\Enums\PaymentMethod;
use Database\Factories\DepositFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property DepositStatus $status Larastan doesn't infer enum casts declared via the casts(): array method syntax.
 * @property PaymentMethod $payment_method Larastan doesn't infer enum casts declared via the casts(): array method syntax.
 * @property Carbon|null $paid_at
 * @property Carbon|null $finalized_at
 */
#[Fillable([
    'cat_id', 'owner_id', 'name', 'email', 'phone', 'amount', 'currency',
    'status', 'provider', 'provider_reference', 'payment_link_url',
    'payment_method', 'created_by', 'paid_at', 'finalized_at',
])]
class Deposit extends Model
{
    /** @use HasFactory<DepositFactory> */
    use HasFactory;

    /**
     * Matches Stripe Checkout's own default session lifetime (see
     * StripeGateway::createCheckout(), which doesn't override expires_at) —
     * a pending deposit older than this can only be an abandoned/expired
     * checkout, never one still legitimately in progress. Shared by
     * ReconcilePendingDeposits and blocksNewReservation() so both agree on
     * what "still active" means.
     */
    public const PENDING_EXPIRY_HOURS = 24;

    protected function casts(): array
    {
        return [
            'status' => DepositStatus::class,
            'payment_method' => PaymentMethod::class,
            'paid_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Cat, $this>
     */
    public function cat(): BelongsTo
    {
        return $this->belongsTo(Cat::class);
    }

    /**
     * @return BelongsTo<Owner, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    /**
     * Null when created through the public checkout flow.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * True when this cat already has a deposit actively holding it — paid,
     * or pending and not yet old enough to be considered an abandoned
     * checkout (see PENDING_EXPIRY_HOURS). Used to refuse a second deposit
     * (public or admin) for the same cat — see CatIsAvailableForDeposit.
     */
    public static function blocksNewReservation(int $catId): bool
    {
        return static::query()
            ->where('cat_id', $catId)
            ->where(function ($query): void {
                $query->where('status', DepositStatus::Paid)
                    ->orWhere(function ($query): void {
                        $query->where('status', DepositStatus::Pending)
                            ->where('created_at', '>', now()->subHours(self::PENDING_EXPIRY_HOURS));
                    });
            })
            ->exists();
    }
}
