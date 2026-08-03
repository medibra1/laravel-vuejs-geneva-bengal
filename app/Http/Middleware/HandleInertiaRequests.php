<?php

namespace App\Http\Middleware;

use App\Models\Page;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Honeypot\Honeypot;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'roles' => $request->user()?->getRoleNames() ?? [],
            ],
            'locale' => app()->getLocale(),
            // Only the info/adoption dropdown sub-menus are CMS-driven —
            // top-level nav (Accueil/Chatons/À propos/Contact/Galerie)
            // stays hardcoded in PublicLayout.vue, per CLAUDE.md.
            'menuPages' => Page::query()
                ->where('is_published', true)
                ->whereNotNull('menu_group')
                ->orderBy('menu_group')
                ->orderBy('order')
                ->get(['id', 'slug', 'menu_group', 'order', 'title']),
            // Shared globally (not per-controller) since more than one
            // public form needs it (contact, newsletter signup).
            'honeypot' => app(Honeypot::class),
        ];
    }
}
