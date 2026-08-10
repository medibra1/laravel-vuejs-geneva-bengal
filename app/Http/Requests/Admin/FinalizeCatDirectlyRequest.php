<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FinalizeCatDirectlyRequest extends FormRequest
{
    /**
     * Route middleware (role:super_admin) already gates access to this
     * endpoint.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same owner_id/new_owner shape as FinalizeDepositRequest, plus cat_id
     * since there's no existing Deposit route-model-binding to hang this
     * off of — this action starts from the cat, not a deposit. Unlike
     * FinalizeDepositRequest, an owner is never optional here: there's no
     * deposit that might already carry one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cat_id' => ['required', 'exists:cats,id'],
            'owner_id' => ['required_without:new_owner', 'nullable', 'exists:owners,id'],
            'new_owner' => ['required_without:owner_id', 'nullable', 'array'],
            'new_owner.first_name' => ['required_with:new_owner', 'string', 'max:255'],
            'new_owner.last_name' => ['required_with:new_owner', 'string', 'max:255'],
            'new_owner.email' => ['required_with:new_owner', 'email', 'max:255', 'unique:owners,email'],
            'new_owner.phone' => ['nullable', 'string', 'max:50'],
            'new_owner.city' => ['nullable', 'string', 'max:255'],
        ];
    }
}
