<?php

namespace App\Http\Requests\Admin\Cats;

use App\Enums\CatSex;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBreederCatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Route middleware (role:admin|super_admin) already gates access to
     * this endpoint.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * No `type` field — BreederCatController always forces
     * CatType::Breeder server-side. No price/available_at/status either:
     * those are adoption concepts, not relevant to a breeding cat (see
     * CLAUDE.md).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sex' => ['required', Rule::enum(CatSex::class)],
            'color_id' => ['required', 'exists:colors,id'],
            'second_color_id' => ['nullable', 'different:color_id', 'exists:colors,id'],
            'description.fr' => ['nullable', 'string'],
            'description.en' => ['nullable', 'string'],
            'birth_date' => ['nullable', 'date'],
            'eye_color' => ['nullable', 'string', 'max:255'],
            'diet' => ['nullable', 'string', 'max:255'],
            'litter_trained' => ['boolean'],
            'neutered' => ['boolean'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:5120'],
        ];
    }
}
