<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\ScreeningCallController;
use App\Http\Controllers\ScreeningResponseController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('screening/{screening:token}', [ScreeningResponseController::class, 'show'])->name('screening.show');
Route::post('screening/{screening:token}', [ScreeningResponseController::class, 'store'])->name('screening.store');

Route::post('screening/{screening:token}/call/session', [ScreeningCallController::class, 'session'])->name('screening.call.session');
Route::post('screening/{screening:token}/call/complete', [ScreeningCallController::class, 'complete'])->name('screening.call.complete');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('licenses', [LicenseController::class, 'index'])->name('licenses.index');
    Route::get('licenses/{license}', [LicenseController::class, 'show'])->name('licenses.show');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
