<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSettingsRequest;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SiteSettingController extends Controller
{
    /**
     * Fixed set of settings this form manages — see UpdateSiteSettingsRequest.
     *
     * @var list<string>
     */
    private const KEYS = [
        'social_facebook', 'social_instagram', 'social_youtube', 'social_tiktok',
        'address', 'phone', 'email', 'deposit_amount', 'price_range_min', 'price_range_max',
        'default_seo_title', 'default_seo_description',
    ];

    /**
     * UpdateSiteSettingsRequest's 'integer' rule only *validates* that the
     * submitted string represents an integer — it never casts the value
     * itself, so $request->safe() still hands back the string "50000".
     * SiteSetting::set()'s array cast then JSON-encodes that string as-is
     * ('"50000"', not '50000'), so a later SiteSetting::get() decodes it
     * back into the *string* "50000" rather than the int 50000 — exactly
     * the bug that broke stripe.elements({ amount }), which requires a
     * real number. Cast explicitly here rather than relying on the
     * validation rule to have done it.
     *
     * @var list<string>
     */
    private const INTEGER_KEYS = ['deposit_amount', 'price_range_min', 'price_range_max'];

    public function edit(): Response
    {
        $settings = collect(self::KEYS)
            ->mapWithKeys(fn (string $key) => [$key => SiteSetting::get($key)])
            ->all();

        return Inertia::render('Admin/Settings/Edit', [
            'settings' => $settings,
            'logoUrl' => $this->logoSetting()->getFirstMediaUrl('logo') ?: null,
        ]);
    }

    public function update(UpdateSiteSettingsRequest $request): RedirectResponse
    {
        foreach ($request->safe()->except('logo') as $key => $value) {
            if ($value !== null && in_array($key, self::INTEGER_KEYS, true)) {
                $value = (int) $value;
            }

            SiteSetting::set($key, $value);
        }

        if ($request->hasFile('logo')) {
            $this->logoSetting()->addMedia($request->file('logo'))->toMediaCollection('logo');
        }

        return redirect()->route('admin.settings.edit')->with('success', 'Réglages mis à jour.');
    }

    /**
     * The key='logo' row is never a plain value — SiteSetting::set()
     * would overwrite it with whatever this form's other fields decided
     * (or null), so the logo's row is resolved directly rather than
     * through that helper.
     */
    private function logoSetting(): SiteSetting
    {
        return SiteSetting::query()->firstOrCreate(['key' => 'logo']);
    }
}
