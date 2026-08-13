<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Internal, public-invisible mechanism that stops a second checkout from
 * starting on the same cat while a first payment is in flight. Distinct
 * from a Deposit/reservation: acquiring a hold never touches the cat's
 * status or availability — the cat stays "disponible" the whole time. Only
 * a confirmed payment turns into a real Deposit + "en_attente" status.
 *
 * @property int $cat_id
 * @property string $payment_intent_id
 * @property Carbon $expires_at
 * @property Carbon $hard_expires_at
 */
#[Fillable(['cat_id', 'payment_intent_id', 'expires_at', 'hard_expires_at'])]
class CheckoutHold extends Model
{
    /**
     * Sliding TTL: how long a hold survives without an extend() ping
     * before an abandoned tab (closed browser, network drop) releases the
     * cat for someone else.
     */
    public const TTL_SECONDS = 180;

    /**
     * Fixed TTL from creation: the ceiling for a tab left open but never
     * actually abandoned. extend() never extends this — without a hard
     * ceiling, a page pinging forever would hold the cat indefinitely.
     */
    public const HARD_TTL_SECONDS = 900;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'hard_expires_at' => 'datetime',
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
     * Attempts to acquire the sole checkout hold for a cat. Locks the cat
     * row for the duration of the transaction so two concurrent callers
     * are serialized rather than racing on the checkout_holds unique index.
     */
    public static function acquire(int $catId, string $paymentIntentId): bool
    {
        return DB::transaction(function () use ($catId, $paymentIntentId) {
            Cat::whereKey($catId)->lockForUpdate()->first();

            $now = now();

            static::query()
                ->where('cat_id', $catId)
                ->where(function ($query) use ($now) {
                    $query->where('expires_at', '<=', $now)
                        ->orWhere('hard_expires_at', '<=', $now);
                })
                ->delete();

            if (static::query()->where('cat_id', $catId)->exists()) {
                return false;
            }

            static::query()->create([
                'cat_id' => $catId,
                'payment_intent_id' => $paymentIntentId,
                'expires_at' => $now->copy()->addSeconds(self::TTL_SECONDS),
                'hard_expires_at' => $now->copy()->addSeconds(self::HARD_TTL_SECONDS),
            ]);

            return true;
        });
    }

    /**
     * Pushes expires_at forward by another TTL_SECONDS window, as long as
     * the hold is still alive on both counts. Returns false if the hold is
     * gone or has already crossed either expiry — a stale hold must never
     * be resurrected, since another visitor may have already reclaimed it.
     * The frontend surfaces a false return as "your checkout session has
     * expired" rather than a silent failure.
     *
     * Named extend() rather than touch(): Eloquent\Model already declares
     * an *instance* touch() (bumps updated_at) via HasTimestamps, and PHP
     * does not allow a subclass to redeclare an inherited method as static
     * with an incompatible signature — doing so is a hard fatal error, not
     * a lint warning, so this isn't a style choice.
     */
    public static function extend(string $paymentIntentId): bool
    {
        $now = now();

        $hold = static::query()
            ->where('payment_intent_id', $paymentIntentId)
            ->where('expires_at', '>', $now)
            ->where('hard_expires_at', '>', $now)
            ->first();

        if (! $hold) {
            return false;
        }

        $hold->update(['expires_at' => $now->copy()->addSeconds(self::TTL_SECONDS)]);

        return true;
    }

    /**
     * Idempotent — a no-op if the hold no longer exists.
     */
    public static function release(string $paymentIntentId): void
    {
        static::query()->where('payment_intent_id', $paymentIntentId)->delete();
    }
}
