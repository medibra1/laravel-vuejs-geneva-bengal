<?php

namespace App\Http\Requests\Admin;

use App\Enums\CatType;
use App\Rules\CatIsAvailableForDeposit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCatToDepositRequest extends FormRequest
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
     * Breeder cats are excluded the same way Admin\DepositController's own
     * reservableCatOptions() excludes them — this is for turning a waiting
     * list entry into an adoption reservation, never a breeder assignment.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cat_id' => [
                'required',
                Rule::exists('cats', 'id')->whereIn('type', [CatType::Kitten->value, CatType::Cat->value]),
                new CatIsAvailableForDeposit,
            ],
        ];
    }
}
