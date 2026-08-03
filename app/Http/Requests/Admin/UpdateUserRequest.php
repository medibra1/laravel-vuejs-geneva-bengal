<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Route middleware (role:super_admin) already gates access to this
     * endpoint. Only the role is editable here — see CLAUDE.md: "Modifier :
     * changer le rôle d'un admin existant, renvoyer le lien de définition
     * de mot de passe."
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
            'role' => ['required', Rule::in(['admin', 'super_admin'])],
        ];
    }
}
