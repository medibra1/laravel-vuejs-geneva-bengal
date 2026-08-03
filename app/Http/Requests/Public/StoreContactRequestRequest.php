<?php

namespace App\Http\Requests\Public;

use App\Enums\ContactReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequestRequest extends FormRequest
{
    /**
     * Public form — anyone may submit it. Spam is filtered upstream by the
     * `Spatie\Honeypot\ProtectAgainstSpam` middleware on the route, not here.
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'reason' => ['required', Rule::enum(ContactReason::class)],
            'cat_id' => ['nullable', 'exists:cats,id'],
            'city' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }
}
