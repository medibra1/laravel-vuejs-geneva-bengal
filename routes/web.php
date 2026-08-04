<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\CatController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\DepositController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\NewsletterController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\StripeWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Spatie\Honeypot\ProtectAgainstSpam;

// Public, SEO-facing pages live under /fr and /en (see CLAUDE.md i18n
// layer 1).
Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath'],
], function (): void {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::get('/chatons-disponibles', [CatController::class, 'index'])->name('cats.index');
    Route::get('/chatons-disponibles/{cat:slug}', [CatController::class, 'show'])->name('cats.show');

    // Explicit literal routes, not a wildcard — see the doc comment on
    // Public\PageController::show() for why a `/{page:slug}` wildcard
    // here would be unsafe.
    Route::get('/a-propos', [PageController::class, 'show'])->defaults('slug', 'a-propos')->name('pages.a-propos');
    Route::get('/contact', [PageController::class, 'show'])->defaults('slug', 'contact')->name('pages.contact');

    // Other menu-driven CMS pages (race, motifs, personnalité, étapes
    // d'adoption...) share this one generic route. Namespaced under
    // /pages/ (not a bare wildcard) so it's a two-segment pattern that
    // can never collide with a single-segment route like /login even if
    // the locale prefix collapses to empty — see the same doc comment.
    Route::get('/pages/{slug}', [PageController::class, 'show'])->name('pages.show');

    Route::post('/contact', [ContactController::class, 'store'])
        ->middleware(ProtectAgainstSpam::class)
        ->name('contact.store');

    Route::post('/newsletter', [NewsletterController::class, 'store'])
        ->middleware(ProtectAgainstSpam::class)
        ->name('newsletter.store');

    Route::post('/deposits', [DepositController::class, 'store'])->name('deposits.store');
    Route::get('/deposits/{deposit}', [DepositController::class, 'show'])->name('deposits.return');
});

// Not locale-prefixed: Stripe doesn't know about /fr or /en, and this
// isn't a page a browser ever visits — see bootstrap/app.php for the
// matching CSRF exemption.
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe');

// The back-office is internal-only: no public/SEO reason to localize its
// URLs. Interface text there is translated client-side via vue-i18n
// instead (see CLAUDE.md i18n layer 3).
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
