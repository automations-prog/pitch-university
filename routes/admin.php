<?php

use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\LicenseStepController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserLicenseController;
use App\Http\Controllers\Admin\VerticalTrainingController;
use App\Http\Controllers\ImpersonateController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:viewAny,'.User::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
        Route::delete('users', [UserController::class, 'bulkDestroy'])->name('users.bulk-destroy');
        Route::post('users/bulk-assign-license', [UserController::class, 'bulkAssignLicense'])->name('users.bulk-assign-license');
        Route::post('users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
        Route::post('users/{user}/licenses/{license}', [UserLicenseController::class, 'store'])->name('users.licenses.store');
        Route::delete('users/{user}/licenses/{license}', [UserLicenseController::class, 'destroy'])->name('users.licenses.destroy');

        Route::resource('vertical-training', VerticalTrainingController::class)->except(['show']);
        Route::delete('vertical-training', [VerticalTrainingController::class, 'bulkDestroy'])->name('vertical-training.bulk-destroy');

        Route::resource('licensing', LicenseController::class)->except(['show']);
        Route::delete('licensing', [LicenseController::class, 'bulkDestroy'])->name('licensing.bulk-destroy');

        Route::scopeBindings()->group(function () {
            Route::post('licensing/{licensing}/steps', [LicenseStepController::class, 'store'])->name('licensing.steps.store');
            Route::put('licensing/{licensing}/steps/{step}', [LicenseStepController::class, 'update'])->name('licensing.steps.update');
            Route::delete('licensing/{licensing}/steps/{step}', [LicenseStepController::class, 'destroy'])->name('licensing.steps.destroy');
            Route::patch('licensing/{licensing}/steps/{step}/move', [LicenseStepController::class, 'move'])->name('licensing.steps.move');
        });

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    });

Route::delete('impersonate', [ImpersonateController::class, 'leave'])
    ->middleware('auth')
    ->name('impersonate.leave');
