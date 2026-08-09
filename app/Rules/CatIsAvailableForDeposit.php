<?php

namespace App\Rules;

use App\Models\Deposit;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Blocks a new deposit for a cat that already has a *paid* one (see
 * Deposit::blocksNewReservation()) — a merely pending deposit no longer
 * blocks: with Stripe PaymentIntents in capture_method: manual (see
 * CLAUDE.md), several visitors can hold a parallel authorization for the
 * same cat at once, and DepositPaymentProcessor::markPaid() is what
 * arbitrates between them at the moment one is about to be captured.
 * Applied on `cat_id` in both the public and admin StoreDepositRequest.
 */
class CatIsAvailableForDeposit implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (Deposit::blocksNewReservation((int) $value)) {
            $fail(__('This kitten has already been reserved.'));
        }
    }
}
