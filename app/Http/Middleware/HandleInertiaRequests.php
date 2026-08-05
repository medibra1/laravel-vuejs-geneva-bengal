<?php

namespace App\Http\Middleware;

use App\Models\Page;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Spatie\Honeypot\Honeypot;
use Tighten\Ziggy\Ziggy;

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
            // Mapped to a plain array rather than passed as a raw Eloquent
            // collection: spatie/laravel-translatable serializes a
            // translatable attribute as the full {fr: ..., en: ...} object
            // on toArray()/JSON serialization (only direct property access
            // resolves it to the current-locale string), which would leak
            // the untranslated object straight into the nav links.
            'menuPages' => Page::query()
                ->where('is_published', true)
                ->whereNotNull('menu_group')
                ->orderBy('menu_group')
                ->orderBy('order')
                ->get(['id', 'slug', 'menu_group', 'order', 'title'])
                ->map(fn (Page $page) => [
                    'id' => $page->id,
                    'slug' => $page->slug,
                    'menu_group' => $page->menu_group,
                    'order' => $page->order,
                    'title' => $page->title,
                ]),
            // Shared globally (not per-controller) since more than one
            // public form needs it (contact, newsletter signup).
            'honeypot' => app(Honeypot::class),
            // hreflang alternates for the current page — empty on
            // non-localized routes (admin, auth), see alternateUrls().
            'alternateUrls' => $this->alternateUrls($request),
            // The client normally reads routes off `window.Ziggy`, injected
            // by the `@routes` Blade directive — that global doesn't exist
            // in the Node SSR process, so ssr.ts needs this prop instead to
            // resolve route() during server rendering. See CLAUDE.md Phase 8.
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function alternateUrls(Request $request): array
    {
        $segments = explode('/', trim($request->path(), '/'));
        $locales = array_keys(LaravelLocalization::getSupportedLocales());

        if (! in_array($segments[0], $locales, true)) {
            return [];
        }

        $rest = implode('/', array_slice($segments, 1));

        return collect($locales)
            ->mapWithKeys(fn (string $locale) => [
                $locale => url('/'.$locale.($rest !== '' ? '/'.$rest : '')),
            ])
            ->all();
    }
}
