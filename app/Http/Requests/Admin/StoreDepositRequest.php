<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use App\Rules\CatIsAvailableForDeposit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepositRequest extends FormRequest
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
     * new_owner.* is only required when new_owner itself is present — the
     * admin may leave both owner_id and new_owner empty (owner gets
     * attached later, at finalize() time) or pick an existing Owner
     * instead.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cat_id' => ['nullable', 'exists:cats,id', new CatIsAvailableForDeposit],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'owner_id' => ['nullable', 'exists:owners,id'],
            'new_owner' => ['nullable', 'array'],
            'new_owner.first_name' => ['required_with:new_owner', 'string', 'max:255'],
            'new_owner.last_name' => ['required_with:new_owner', 'string', 'max:255'],
            'new_owner.email' => ['required_with:new_owner', 'email', 'max:255', 'unique:owners,email'],
            'new_owner.phone' => ['nullable', 'string', 'max:50'],
            'new_owner.city' => ['nullable', 'string', 'max:255'],
        ];
    }
}
