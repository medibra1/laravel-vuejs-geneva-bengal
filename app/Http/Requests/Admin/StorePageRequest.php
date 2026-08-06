<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Mews\Purifier\Facades\Purifier;

class StorePageRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'menu_group' => ['nullable', 'string', 'max:255'],
            'order' => ['nullable', 'integer', 'min:0'],
            'title.fr' => ['required', 'string', 'max:255'],
            'title.en' => ['required', 'string', 'max:255'],
            'body.fr' => ['nullable', 'string'],
            'body.en' => ['nullable', 'string'],
            'meta_title.fr' => ['nullable', 'string', 'max:255'],
            'meta_title.en' => ['nullable', 'string', 'max:255'],
            'meta_description.fr' => ['nullable', 'string', 'max:500'],
            'meta_description.en' => ['nullable', 'string', 'max:500'],
            'is_published' => ['boolean'],
        ];
    }

    /**
     * RichTextEditor.vue's output is trusted client-side but still crosses
     * a trust boundary at save time — sanitize with the 'cms' HTMLPurifier
     * profile (config/purifier.php) so a stored page can never carry a
     * script tag or an on* handler regardless of what produced the HTML.
     */
    protected function passedValidation(): void
    {
        $this->merge([
            'body' => [
                'fr' => Purifier::clean((string) $this->input('body.fr', ''), 'cms'),
                'en' => Purifier::clean((string) $this->input('body.en', ''), 'cms'),
            ],
        ]);
    }
}
