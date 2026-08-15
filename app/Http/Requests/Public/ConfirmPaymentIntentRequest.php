<?php

namespace App\Http\Requests\Public;

use App\Rules\CatIsAvailableForDeposit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Backs the "Pay" click itself (Public\DepositController::confirmIntent()) —
 * distinct from StoreDepositRequest, which only backs arriving on the
 * checkout page. Same fields, since a PaymentIntent needs everything the
 * checkout page already collected; cat_id is re-validated here too, since
 * this is the request where it actually matters (see CLAUDE.md).
 */
class ConfirmPaymentIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'cat_id' => ['nullable', 'exists:cats,id', new CatIsAvailableForDeposit],
        ];
    }
}
