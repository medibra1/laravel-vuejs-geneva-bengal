<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use NielsNumbers\LaravelLocalizer\Facades\Localizer;
use NielsNumbers\LaravelLocalizer\Routing\LocalizerBladeRouteGeneratorV2;
use Stripe\StripeClient;
use Tighten\Ziggy\BladeRouteGenerator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // PaymentGateway is an interface (see CLAUDE.md) precisely so the
        // rest of the app never depends on Stripe directly — swapping
        // providers later only means rebinding this one line.
        $this->app->bind(PaymentGateway::class, fn () => new StripeGateway(
            new StripeClient(config('services.stripe.secret')),
            (string) config('services.stripe.webhook_secret'),
        ));

        // Makes the @routes Blade directive emit a locale-aware manifest,
        // so route() in JS resolves the /fr or /en variant automatically —
        // this project is on tightenco/ziggy ^2.0 (Tighten\Ziggy namespace),
        // hence the V2 adapter.
        $this->app->bind(BladeRouteGenerator::class, LocalizerBladeRouteGeneratorV2::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Vite::prefetch() and the default modulepreload tags both walk the
        // whole manifest dependency graph — including import.meta.glob's
        // dynamic imports of every Pages/**/*.vue chunk — so every page
        // (public included) was preloading/prefetching Admin/* chunks,
        // PrimeVue's heavier components (DataTable, Dialog...), etc. Turning
        // both off leaves dynamic imports working exactly as before (Inertia
        // still fetches each page's own chunk on navigation); it just stops
        // aggressively front-loading chunks the current page never uses.
        Vite::usePreloadTagAttributes(false);

        // laravel-localizer's default locale falls back to
        // config('app.fallback_locale') (= 'en', the Laravel-native
        // "translation missing" fallback — unrelated to which locale a
        // visitor with no locale signal should land on). This app has no
        // lang/ files at all (see CLAUDE.md i18n layers), so
        // fallback_locale has no other effect here — but it still drove an
        // unprefixed "/" visitor to /en instead of /fr without this
        // override. app.locale (='fr') is the actual site default.
        Localizer::setActiveDefaultLocale(config('app.locale'));

        Gate::before(fn (User $user) => $user->hasRole('super_admin') ? true : null);

        // Inertia props aren't a REST API: the extra {"data": ...} envelope
        // JsonResource adds by default just forces every Vue page to unwrap
        // it for no benefit. Paginated collections are unaffected — that
        // shape comes from LengthAwarePaginator::toArray() natively, not
        // from this wrapping mechanism.
        JsonResource::withoutWrapping();
    }
}
