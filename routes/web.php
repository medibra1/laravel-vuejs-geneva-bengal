<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\CatController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\DepositController;
use App\Http\Controllers\Public\GalleryController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\LitterController;
use App\Http\Controllers\Public\NewsletterController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\StripeWebhookController;
use App\Jobs\ReconcileCheckouts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Spatie\Honeypot\ProtectAgainstSpam;

// Public, SEO-facing pages live under /fr and /en (see CLAUDE.md i18n
// layer 1). Route::localize() registers two static routes per definition
// (with_locale.* and without_locale.*), unlike the old package's
// per-request dynamic prefix — this is what makes route:cache safe.
Route::localize(function (): void {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::get('/chatons-disponibles', [CatController::class, 'index'])->name('cats.index');
    // Three URL segments vs. cats.show's two ({cat:slug}) — Laravel routes
    // by segment count, so this never collides with a color slug that
    // happens to also be a cat slug.
    Route::get('/chatons-disponibles/couleur/{color:slug}', [CatController::class, 'filterByColor'])->name('cats.index.color');
    Route::get('/chatons-disponibles/{cat:slug}', [CatController::class, 'show'])->name('cats.show');
    Route::get('/nos-chats-reproducteurs', [CatController::class, 'breeders'])->name('cats.breeders');

    Route::get('/portees-prevues', [LitterController::class, 'index'])->name('litters.index');

    Route::get('/galerie', [GalleryController::class, 'index'])->name('galleries.index');

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
    Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])
        ->name('newsletter.unsubscribe');

    Route::post('/deposits', [DepositController::class, 'store'])
        ->middleware(ProtectAgainstSpam::class)
        ->name('deposits.store');
    // store() needs the form data (name/email/cat_id) that only exists on
    // the POST request that got the visitor here — a raw GET (browser
    // reload, bookmarked/shared URL) has none of that to render the
    // checkout page with, so there's nothing valid to show. Redirects to
    // the kittens list rather than 405ing, so an accidental reload sends
    // the visitor somewhere they can actually restart a reservation from.
    Route::get('/deposits', fn () => redirect()->route('cats.index'));
    // Called from Public/DepositPay.vue at the "Pay" click, not on page
    // load — see DepositController::confirmIntent() and CLAUDE.md. A plain
    // JSON endpoint, not an Inertia page: this is a background fetch that
    // returns a client_secret, never a navigation.
    Route::post('/deposits/confirm-intent', [DepositController::class, 'confirmIntent'])
        ->middleware(ProtectAgainstSpam::class)
        ->name('deposits.confirm-intent');
    // Keyed on the Stripe PaymentIntent id, not a Deposit id: no Deposit is
    // created up front (see CLAUDE.md) — the visitor lands here straight
    // from the checkout page, before the webhook has necessarily built one.
    // See DepositController::show().
    Route::get('/deposits/return/{paymentIntentId}', [DepositController::class, 'show'])->name('deposits.return');
});

// Not locale-prefixed: Stripe doesn't know about /fr or /en, and this
// isn't a page a browser ever visits — see bootstrap/app.php for the
// matching CSRF exemption.
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe');

// Infomaniak's shared-hosting task scheduler only calls a URL (no real
// crontab there, see DEPLOY.md #4) — this stands in for both the classic
// `* * * * * php artisan schedule:run` cron entry and a queue worker
// daemon (neither of which shared hosting can run). Token-gated (not left
// open) so a discovered URL can't be used to force-run due scheduled
// tasks or drain the queue on demand; throttled on top as a second,
// cheap guard. GET-only, so Laravel's CSRF guard (which only checks
// state-changing verbs) never applies here regardless of the `web`
// middleware group's session/cookie middleware being harmlessly present.
Route::get('/cron/run', function (Request $request) {
    abort_unless(
        config('app.cron_secret') && hash_equals((string) config('app.cron_secret'), (string) $request->query('token')),
        403
    );

    Artisan::call('schedule:run');
    Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 50]);

    // schedule:run only actually executes ReconcileCheckouts when this
    // request happens to land on one of its fixed :00/:15/:30/:45 slots
    // (Schedule::job(...)->everyFifteenMinutes(), see routes/console.php)
    // — every other minute it's a silent no-op that still answers "OK",
    // which is indistinguishable from a real run without checking the
    // database. Infomaniak's task scheduler (see DEPLOY.md §4) only
    // guarantees calling this URL at least every 15 minutes, not at those
    // exact slots, so relying on schedule:run alone left up to ~15 minutes
    // where a stale PaymentIntentTracking row sat unresolved even once its
    // own grace period had passed. Dispatched directly here as well so
    // every call to this endpoint actually reconciles, not just the ones
    // that happen to align with the schedule's slots — safe to run this
    // often since the job is idempotent (see CLAUDE.md).
    dispatch_sync(new ReconcileCheckouts);

    // Same problem as ReconcileCheckouts above — schedule:run's own
    // ->monthly() slot (see routes/console.php) is not guaranteed to
    // align with any particular call to this endpoint on shared hosting.
    // Unlike ReconcileCheckouts, this one doesn't need to run on every
    // request (it's a single DELETE over the whole table, and monthly is
    // already generous) — Cache::add() atomically claims the run for the
    // next 30 days so two /cron/run requests landing close together can't
    // both trigger it, without needing a real cron/worker to track "did
    // this already run this month".
    if (Cache::add('activitylog-clean-last-run', now(), now()->addDays(30))) {
        Artisan::call('activitylog:clean', ['--force' => true]);
    }

    return response('OK', 200);
})->middleware('throttle:10,1')->name('cron.run');

// One canonical sitemap listing every locale variant of every page (via
// hreflang alternates) rather than a separate /fr/sitemap.xml, /en/sitemap.xml.
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// The back-office is internal-only: no public/SEO reason to localize its
// URLs. Interface text there is translated client-side via vue-i18n
// instead (see CLAUDE.md i18n layer 3). The dashboard itself now lives
// under /admin (see routes/admin.php) — kept registered under the
// unprefixed route name "dashboard" there so Breeze's own auth
// controllers (email verification, login redirects...) don't need to
// know it moved.

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
