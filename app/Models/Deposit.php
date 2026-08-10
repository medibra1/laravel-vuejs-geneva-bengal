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
 * @property ?PaymentMethod $payment_method Nullable — an admin-recorded reservation can be created with the method "to be defined later". Larastan doesn't infer enum casts declared via the casts(): array method syntax either way.
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
     * A pending deposit older than this is considered an abandoned
     * checkout — its PaymentIntent authorization (card holds typically last
     * up to 7 days on Stripe, TWINT much less) is assumed dead and the cat
     * it was holding is released. Used by ReconcilePendingDeposits'
     * expiry check only — no longer relevant to blocksNewReservation()
     * below, which stopped caring about `pending` deposits entirely.
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
     * True when this cat already has a *paid* deposit. A merely `pending`
     * deposit no longer blocks a new one: with Stripe PaymentIntents in
     * capture_method: manual (see CLAUDE.md), several visitors can hold a
     * parallel authorization for the same cat at once — only an actual
     * payment locks it. DepositPaymentProcessor::markPaid() is what
     * arbitrates between concurrent authorizations at the moment one of
     * them is about to be captured. Used by CatIsAvailableForDeposit
     * (public and admin StoreDepositRequest).
     */
    public static function blocksNewReservation(int $catId): bool
    {
        return static::query()
            ->where('cat_id', $catId)
            ->where('status', DepositStatus::Paid)
            ->exists();
    }
}
