<?php

use App\Http\Controllers\Admin\CatController;
use App\Http\Controllers\Admin\ContactRequestController;
use App\Http\Controllers\Admin\FaqItemController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\LitterController;
use App\Http\Controllers\Admin\OwnerController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'role:admin|super_admin'])
    ->group(function (): void {
        Route::resource('cats', CatController::class)->except('show');
        Route::resource('owners', OwnerController::class)->except('show');
        Route::resource('litters', LitterController::class)->except('show');
        Route::resource('galleries', GalleryController::class)->except('show');
        Route::resource('pages', PageController::class)->except('show');
        Route::resource('faq-items', FaqItemController::class)->except('show');
        Route::resource('testimonials', TestimonialController::class)->except('show');
        Route::resource('contact-requests', ContactRequestController::class)->only(['index', 'update', 'destroy']);
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
    });
