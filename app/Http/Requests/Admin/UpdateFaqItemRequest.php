<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFaqItemRequest extends FormRequest
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
            'question.fr' => ['required', 'string', 'max:255'],
            'question.en' => ['required', 'string', 'max:255'],
            'answer.fr' => ['required', 'string'],
            'answer.en' => ['required', 'string'],
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
