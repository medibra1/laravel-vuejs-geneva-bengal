<?php

use App\Http\Controllers\Admin\CatController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'role:admin|super_admin'])
    ->group(function (): void {
        Route::resource('cats', CatController::class)->except('show');
    });
