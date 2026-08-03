<?php

use App\Http\Controllers\Admin\CatController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\LitterController;
use App\Http\Controllers\Admin\OwnerController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'role:admin|super_admin'])
    ->group(function (): void {
        Route::resource('cats', CatController::class)->except('show');
        Route::resource('owners', OwnerController::class)->except('show');
        Route::resource('litters', LitterController::class)->except('show');
        Route::resource('galleries', GalleryController::class)->except('show');
    });
