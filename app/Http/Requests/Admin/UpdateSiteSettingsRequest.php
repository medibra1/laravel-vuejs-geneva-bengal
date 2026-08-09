<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteSettingsRequest extends FormRequest
{
    /**
     * Route middleware (role:super_admin) already gates access to this
     * endpoint — site_settings is explicitly super_admin-only per
     * CLAUDE.md's role split.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A fixed, known set of settings — not an arbitrary key/value CRUD —
     * matching the specific settings CLAUDE.md calls out (social links,
     * address, deposit amount, price range, default SEO texts).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'social_facebook' => ['nullable', 'url', 'max:255'],
            'social_instagram' => ['nullable', 'url', 'max:255'],
            'social_youtube' => ['nullable', 'url', 'max:255'],
            'social_tiktok' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'deposit_amount' => ['nullable', 'integer', 'min:0'],
            'price_range_min' => ['nullable', 'integer', 'min:0'],
            'price_range_max' => ['nullable', 'integer', 'min:0', 'gte:price_range_min'],
            'default_seo_title' => ['nullable', 'string', 'max:255'],
            'default_seo_description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
