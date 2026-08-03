<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
