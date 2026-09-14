<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LicenseController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('licenses', [LicenseController::class, 'index'])->name('licenses.index');
    Route::get('licenses/{license}', [LicenseController::class, 'show'])->name('licenses.show');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
