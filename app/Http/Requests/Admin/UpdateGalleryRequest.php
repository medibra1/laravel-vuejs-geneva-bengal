<?php

namespace App\Http\Requests\Admin;

use App\Enums\GalleryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
     * working unchanged. `position` is defaulted to the gallery's current
     * value for the same reason the uniqueness rule below needs a
     * concrete value to check — see StoreGalleryRequest.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->input('type', $this->route('gallery')->type->value),
            'position' => $this->input('position', $this->route('gallery')->position),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'caption' => ['nullable', 'string', 'max:255'],
            // Backs the (type, position) unique DB index — ignores this
            // gallery's own row, otherwise saving the form unchanged would
            // fail against its own existing position.
            'position' => [
                'required',
                'integer',
                'min:0',
                Rule::unique('galleries')
                    ->where(fn ($query) => $query->where('type', $this->input('type')))
                    ->ignore($this->route('gallery')),
            ],
            'image' => ['nullable', 'image', 'max:5120'],
            'type' => ['required', new Enum(GalleryType::class)],
        ];
    }
}
