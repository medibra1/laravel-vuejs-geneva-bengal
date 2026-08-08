<?php

namespace App\Http\Requests\Admin\Cats;

use App\Enums\CatSex;
use App\Enums\CatStatus;
use App\Enums\CatType;
use App\Models\Cat;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdoptionCatRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([CatType::Kitten->value, CatType::Cat->value])],
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
            // "adopte" is only ever reached through Admin\DepositController::finalize()
            // (a paid deposit, linked to an owner) — a *new* transition to it is
            // rejected here, but resubmitting the form for a cat that's already
            // adopted (e.g. fixing a typo in its name) must not break — the select
            // still carries the cat's current status even though "Adopté" is no
            // longer one of its options (see Form.vue's statusOptions).
            'status' => [
                'nullable',
                Rule::in([CatStatus::Available->value, CatStatus::Pending->value, CatStatus::Adopted->value]),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $cat = $this->route('cat');

                    if ($value === CatStatus::Adopted->value && ! ($cat instanceof Cat && $cat->status === CatStatus::Adopted->value)) {
                        $fail(__('The "adopted" status can only be set by finalizing a paid reservation.'));
                    }
                },
            ],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:5120'],
        ];
    }
}
