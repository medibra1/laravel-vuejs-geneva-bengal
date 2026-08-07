<?php

namespace App\Rules;

use App\Models\Deposit;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Blocks a second deposit for a cat that already has one actively holding
 * it (see Deposit::blocksNewReservation()) — without this, two visitors
 * could both pay a deposit for the same kitten before either payment is
 * confirmed. Applied on `cat_id` in both the public and admin
 * StoreDepositRequest.
 */
class CatIsAvailableForDeposit implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (Deposit::blocksNewReservation((int) $value)) {
            $fail(__('This kitten already has a reservation in progress.'));
        }
    }
}
