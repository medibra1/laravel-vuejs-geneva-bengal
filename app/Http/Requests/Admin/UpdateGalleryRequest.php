<?php

namespace App\Http\Requests\Admin;

use App\Enums\GalleryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateGalleryRequest extends FormRequest
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
     * Defaults `type` to the gallery's current value when omitted, so the
     * existing gallery-editing flow (no `type` field in the form) keeps
     * working unchanged.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->input('type', $this->route('gallery')->type->value),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'caption' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:5120'],
            'type' => ['required', new Enum(GalleryType::class)],
        ];
    }
}
