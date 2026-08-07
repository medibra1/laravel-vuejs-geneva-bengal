<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FinalizeDepositRequest extends FormRequest
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
     * Both fields are optional here: if the deposit already has an
     * owner_id (set back at creation), the controller reuses it and
     * neither of these is needed at all.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
