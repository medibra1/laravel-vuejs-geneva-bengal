<?php

namespace App\Http\Middleware;

use App\Models\Color;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;
use NielsNumbers\LaravelLocalizer\Facades\Localizer;
use NielsNumbers\LaravelLocalizer\Routing\LocalizerZiggyV2;
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
            // Session flash messages set by controllers via ->with('success'|'error', ...)
            // — read once by FlashToast.vue (mounted in both AdminLayout.vue and
            // PublicLayout.vue) and shown as a toast. Only read here, never written:
            // Laravel's session flash bag already clears itself after one request.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
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
            'phone' => SiteSetting::get('phone'),
            'email' => SiteSetting::get('email'),
            // Single row + single media lookup — negligible next to the
            // plain SiteSetting::get() calls above, unlike heroSlides
            // (Gallery::query()->with('media')->get(), a real query per
            // request) which is deliberately per-controller instead — see
            // SharesHeroSlides.
            'logoUrl' => SiteSetting::query()->where('key', 'logo')->first()?->getFirstMediaUrl('logo') ?: null,
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
            // LocalizerZiggyV2 (not the plain Ziggy class) so the SSR
            // manifest is locale-aware too, same as the @routes directive
            // (bound to LocalizerBladeRouteGeneratorV2 in AppServiceProvider)
            // — Ziggy's own BladeRouteGenerator instantiates `new Ziggy`
            // directly, so that container binding alone doesn't cover this
            // direct instantiation here.
            'ziggy' => fn () => [
                ...(new LocalizerZiggyV2)->toArray(),
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
        if (! Route::isLocalized()) {
            return [];
        }

        return collect(Localizer::supportedLocales())
            ->mapWithKeys(fn (string $locale) => [
                // localizedSwitcherUrl (not localizedUrl): always emits a
                // prefixed URL, even for the default locale — matches the
                // pre-migration behavior where every locale always got an
                // explicit /fr or /en prefix (hide_default_locale is off
                // here, but localizedUrl() would still special-case this
                // if it were ever turned on).
                $locale => Route::localizedSwitcherUrl($locale),
            ])
            ->all();
    }
}
