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
        'social_facebook', 'social_instagram', 'social_youtube', 'social_pinterest',
        'address', 'deposit_amount', 'price_range_min', 'price_range_max',
        'default_seo_title', 'default_seo_description',
    ];

    public function edit(): Response
    {
        $settings = collect(self::KEYS)
            ->mapWithKeys(fn (string $key) => [$key => SiteSetting::get($key)])
            ->all();

        return Inertia::render('Admin/Settings/Edit', [
            'settings' => $settings,
        ]);
    }

    public function update(UpdateSiteSettingsRequest $request): RedirectResponse
    {
        foreach ($request->validated() as $key => $value) {
            SiteSetting::set($key, $value);
        }

        return redirect()->route('admin.settings.edit')->with('success', __('Settings updated.'));
    }
}
