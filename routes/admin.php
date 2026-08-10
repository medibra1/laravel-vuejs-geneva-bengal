<?php

use App\Http\Controllers\Admin\Cats\AdoptionCatController;
use App\Http\Controllers\Admin\Cats\BreederCatController;
use App\Http\Controllers\Admin\ContactRequestController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepositController;
use App\Http\Controllers\Admin\EditorUploadController;
use App\Http\Controllers\Admin\FaqItemController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\LitterController;
use App\Http\Controllers\Admin\NewsletterSubscriberController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OwnerController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// Kept outside the ->name('admin.') group below on purpose: Breeze's own
// auth controllers (email verification, login redirects...) all target
// route('dashboard') by that exact unprefixed name. Only the URL moves
// under /admin — the route name stays "dashboard" so none of that
// generated code needs to know it moved.
Route::prefix('admin')
    ->middleware(['auth', 'verified', 'role:admin|super_admin'])
    ->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');
    });

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'role:admin|super_admin'])
    ->group(function (): void {
        // NotificationBell.vue — refreshed via HandleInertiaRequests'
        // shared prop on every navigation, no polling.
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

        // Two admin sections sharing one Cat model (see CLAUDE.md): kittens/
        // cats up for adoption vs. breeding cats. Each ->parameters() call
        // keeps the route param named {cat} (not {adoption}/{breeders},
        // Laravel's default guess from the last URI segment) so it matches
        // the Cat $cat type-hint in both controllers.
        Route::resource('cats/adoption', AdoptionCatController::class)
            ->except('show')
            ->names('cats.adoption')
            ->parameters(['adoption' => 'cat']);
        Route::delete('cats/adoption/{cat}/photos/{media}', [AdoptionCatController::class, 'destroyPhoto'])->name('cats.adoption.photos.destroy');

        Route::resource('cats/breeders', BreederCatController::class)
            ->except('show')
            ->names('cats.breeders')
            ->parameters(['breeders' => 'cat']);
        Route::delete('cats/breeders/{cat}/photos/{media}', [BreederCatController::class, 'destroyPhoto'])->name('cats.breeders.photos.destroy');

        Route::resource('owners', OwnerController::class)->except('show');
        Route::resource('litters', LitterController::class)->except('show');
        Route::resource('galleries', GalleryController::class)->except('show');
        Route::resource('pages', PageController::class)->except('show');
        // RichTextEditor.vue's image button, used on pages.body only.
        Route::post('media/upload', [EditorUploadController::class, 'store'])->name('media.upload');
        Route::resource('faq-items', FaqItemController::class)->except('show');
        Route::resource('testimonials', TestimonialController::class)->except('show');
        Route::resource('contact-requests', ContactRequestController::class)->only(['index', 'update', 'destroy']);
        // Kept separate from contact-requests per CLAUDE.md — different
        // module, different legal obligation (unsubscribe must work).
        Route::get('newsletter-subscribers', [NewsletterSubscriberController::class, 'index'])->name('newsletter-subscribers.index');
        Route::get('newsletter-subscribers/export', [NewsletterSubscriberController::class, 'export'])->name('newsletter-subscribers.export');
        Route::patch('newsletter-subscribers/{newsletterSubscriber}/toggle-unsubscribed', [NewsletterSubscriberController::class, 'toggleUnsubscribed'])->name('newsletter-subscribers.toggle-unsubscribed');
        // Refunding (below) is super_admin-only per CLAUDE.md, but viewing/
        // creating/finalizing isn't — deposits are business content like
        // the rest of this group.
        Route::get('deposits', [DepositController::class, 'index'])->name('deposits.index');
        Route::get('deposits/create', [DepositController::class, 'create'])->name('deposits.create');
        Route::post('deposits', [DepositController::class, 'store'])->name('deposits.store');
        Route::post('deposits/{deposit}/mark-paid', [DepositController::class, 'markPaid'])->name('deposits.mark-paid');
        // Re-prompts for the current password if the last confirmation is
        // stale (see config('auth.password_timeout')) — these two touch
        // money/ownership, mark-paid/assign-cat above don't.
        Route::post('deposits/{deposit}/verify-stripe', [DepositController::class, 'verifyStripe'])
            ->middleware('password.confirm')
            ->name('deposits.verify-stripe');
        Route::post('deposits/{deposit}/finalize', [DepositController::class, 'finalize'])
            ->middleware('password.confirm')
            ->name('deposits.finalize');
        // Turns a waiting-list entry (cat_id null) into a reservation for a
        // specific kitten once one becomes available — see CLAUDE.md's
        // "assigner un chat" bonus.
        Route::post('deposits/{deposit}/assign-cat', [DepositController::class, 'assignCat'])->name('deposits.assign-cat');
    });

// site_settings is super_admin-only per CLAUDE.md's role split — deliberately
// not part of the admin|super_admin group above.
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'role:super_admin'])
    ->group(function (): void {
        Route::get('settings', [SiteSettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SiteSettingController::class, 'update'])->name('settings.update');

        // index/create/edit are just reading data/rendering a form — only
        // the actions that actually create/change/remove an admin account
        // need a fresh password confirmation.
        Route::resource('users', UserController::class)->except('show')
            ->middlewareFor(['store', 'update', 'destroy'], 'password.confirm');
        Route::post('users/{user}/resend-reset-link', [UserController::class, 'resendResetLink'])
            ->middleware('password.confirm')
            ->name('users.resend-reset-link');
        Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
            ->middleware('password.confirm')
            ->name('users.toggle-active');

        Route::post('deposits/{deposit}/refund', [DepositController::class, 'refund'])
            ->middleware('password.confirm')
            ->name('deposits.refund');
        // Undoes a paid deposit — releases the cat (en_attente or already
        // adopte) back to disponible. Same sensitivity/group as refund()
        // above: this defeats a confirmed reservation/adoption, not just a
        // display toggle. See CLAUDE.md.
        Route::post('deposits/{deposit}/cancel', [DepositController::class, 'cancel'])
            ->middleware('password.confirm')
            ->name('deposits.cancel');
        // Bypasses the Deposit-driven flow entirely — a gift, an in-person
        // sale with no online deposit, etc. Still creates a Deposit under
        // the hood (see DepositPaymentProcessor::finalizeDirectly()), just
        // not through any of the routes above. Same sensitivity/group as
        // refund()/cancel(): this directly marks a cat adopted.
        Route::post('cats/finalize-directly', [DepositController::class, 'finalizeDirectly'])
            ->middleware('password.confirm')
            ->name('cats.finalize-directly');
    });
