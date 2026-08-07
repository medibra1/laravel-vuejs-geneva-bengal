<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Newsletter\BrevoNewsletterService;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

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

        $this->app->singleton(BrevoNewsletterService::class, fn () => new BrevoNewsletterService(
            config('services.brevo.key'),
            config('services.brevo.list_id') ? (int) config('services.brevo.list_id') : null,
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::before(fn (User $user) => $user->hasRole('super_admin') ? true : null);

        // Inertia props aren't a REST API: the extra {"data": ...} envelope
        // JsonResource adds by default just forces every Vue page to unwrap
        // it for no benefit. Paginated collections are unaffected — that
        // shape comes from LengthAwarePaginator::toArray() natively, not
        // from this wrapping mechanism.
        JsonResource::withoutWrapping();
    }
}
