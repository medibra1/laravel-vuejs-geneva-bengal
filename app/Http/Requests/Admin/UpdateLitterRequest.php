<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLitterRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sire_cat_id' => ['nullable', 'different:dam_cat_id', 'exists:cats,id'],
            'dam_cat_id' => ['nullable', 'exists:cats,id'],
            'expected_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
