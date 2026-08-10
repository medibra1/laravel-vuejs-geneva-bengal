<?php

namespace App\Http\Requests\Admin;

use App\Models\Deposit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkDepositPaidRequest extends FormRequest
{
    /**
     * Route middleware (role:admin|super_admin) already gates access to
     * this endpoint.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * payment_method is only required here when the routed Deposit itself
     * doesn't already have one — an admin-recorded reservation can be
     * created with the method "to be defined later" (see
     * StoreDepositRequest), resolved at this exact moment instead. A
     * deposit that already has a payment_method ignores whatever this
     * field holds — Admin\DepositController::markPaid() never overwrites
     * an existing choice.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_method' => [
                Rule::requiredIf(function (): bool {
                    $deposit = $this->route('deposit');

                    return $deposit instanceof Deposit && $deposit->payment_method === null;
                }),
                'nullable',
                Rule::in(['cash', 'bank_transfer', 'twint_manual']),
            ],
        ];
    }
}
