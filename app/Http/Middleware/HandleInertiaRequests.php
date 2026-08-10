<?php

namespace App\Http\Middleware;

use App\Models\Color;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
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
            // Admin-only, but shared globally rather than per-controller so
            // NotificationBell.vue (mounted once in AdminLayout.vue) always
            // has fresh data without a dedicated endpoint — refreshed on
            // every Inertia navigation, no polling needed.
            'notifications' => $this->notifications($request),
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
            // Shared globally: the nav's "Chaton Bengal Disponible" dropdown
            // needs the color list on every public page, not just the
            // listing page itself.
            'colors' => Color::query()->orderBy('name')->get(['id', 'name', 'slug', 'hex_code']),
            // Shared globally rather than per-controller: the header,
            // footer and homepage "follow us" sections all need these
            // links too, not just the contact page.
            'socialLinks' => [
                'facebook' => SiteSetting::get('social_facebook'),
                'instagram' => SiteSetting::get('social_instagram'),
                'youtube' => SiteSetting::get('social_youtube'),
                'tiktok' => SiteSetting::get('social_tiktok'),
            ],
            // Same reasoning — the footer shows it on every public page,
            // not just Contact.
            'address' => SiteSetting::get('address'),
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
     * Null on any page with no authenticated user (all public pages, plus
     * the admin login screen itself) — the frontend treats a null
     * `notifications` prop as "hide the bell" rather than "zero unread".
     *
     * @return array{unread_count: int, recent: array<int, array<string, mixed>>}|null
     */
    private function notifications(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'recent' => $user->notifications()->latest()->take(10)->get()
                ->map(fn (DatabaseNotification $notification) => [
                    'id' => $notification->id,
                    'type' => $notification->data['type'] ?? null,
                    'title' => $notification->data['title'] ?? null,
                    'message' => $notification->data['message'] ?? null,
                    'url' => $notification->data['url'] ?? null,
                    'reason' => $notification->data['reason'] ?? null,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                ])
                ->all(),
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
