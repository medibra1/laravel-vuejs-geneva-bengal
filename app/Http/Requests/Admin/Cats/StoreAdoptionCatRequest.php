<?php

namespace App\Http\Requests\Admin\Cats;

use App\Enums\CatSex;
use App\Enums\CatStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdoptionCatRequest extends FormRequest
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
     * No `type` field — AdoptionCatController::store() always forces
     * CatType::Kitten server-side, same as BreederCatController does for
     * CatType::Breeder. A cat created here is always a "chaton"; an
     * existing "chat"-type record (legacy data) stays manageable through
     * this section but its type can no longer be set from this form.
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
            'price' => ['nullable', 'integer', 'min:0'],
            'birth_date' => ['nullable', 'date'],
            'eye_color' => ['nullable', 'string', 'max:255'],
            'available_at' => ['nullable', 'date'],
            'diet' => ['nullable', 'string', 'max:255'],
            'litter_trained' => ['boolean'],
            'neutered' => ['boolean'],
            'status' => ['nullable', Rule::enum(CatStatus::class)],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:5120'],
        ];
    }
}
