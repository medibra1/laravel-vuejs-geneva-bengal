<?php

namespace App\Http\Requests\Admin;

use App\Enums\GalleryType;
use App\Rules\GalleryTypeLimitNotReached;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreGalleryRequest extends FormRequest
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
     * Defaults `type` to Gallery when omitted, so the existing
     * gallery-creation flow (no `type` field in the form) keeps working
     * unchanged.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->input('type', GalleryType::Gallery->value),
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
            'image' => ['required', 'image', 'max:5120'],
            'type' => ['required', new Enum(GalleryType::class), new GalleryTypeLimitNotReached],
        ];
    }
}
