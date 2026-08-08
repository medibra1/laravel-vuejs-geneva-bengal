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
        Route::post('deposits/{deposit}/finalize', [DepositController::class, 'finalize'])->name('deposits.finalize');
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

        Route::resource('users', UserController::class)->except('show');
        Route::post('users/{user}/resend-reset-link', [UserController::class, 'resendResetLink'])->name('users.resend-reset-link');
        Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');

        Route::post('deposits/{deposit}/refund', [DepositController::class, 'refund'])->name('deposits.refund');
    });
