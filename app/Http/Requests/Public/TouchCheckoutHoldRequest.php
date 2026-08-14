<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by DepositController::touchHold()/releaseHold() — both act on a
 * payment_intent_id and nothing else.
 */
class TouchCheckoutHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'payment_intent_id' => ['required', 'string'],
        ];
    }
}
