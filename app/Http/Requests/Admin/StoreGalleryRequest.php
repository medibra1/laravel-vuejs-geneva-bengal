<?php

namespace App\Http\Requests\Admin;

use App\Enums\GalleryType;
use App\Rules\GalleryTypeLimitNotReached;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
     * unchanged. `position` is also defaulted here (rather than left null)
     * so the uniqueness rule below validates the value that will actually
     * be persisted — the column itself also defaults to 0, so a null here
     * would otherwise slip past validation and still collide in the DB.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->input('type', GalleryType::Gallery->value),
            'position' => $this->input('position', 0),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'caption' => ['nullable', 'string', 'max:255'],
            // Backs the (type, position) unique DB index added on the
            // galleries table — without this, two entries of the same type
            // left at the same position (e.g. the form's own position=0
            // default, twice) would 500 on a raw QueryException instead of
            // a normal validation error.
            'position' => [
                'required',
                'integer',
                'min:0',
                Rule::unique('galleries')->where(fn ($query) => $query->where('type', $this->input('type'))),
            ],
            'image' => ['required', 'image', 'max:5120'],
            'type' => ['required', new Enum(GalleryType::class), new GalleryTypeLimitNotReached],
        ];
    }
}
