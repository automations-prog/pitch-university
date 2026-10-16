<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\CallLogController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\ExamRetakeController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\LicenseStepController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ScreeningController;
use App\Http\Controllers\Admin\ScreeningResponseController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TeamInvitationController;
use App\Http\Controllers\Admin\TrainingTrackController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserTrackController;
use App\Http\Controllers\Admin\VerticalTrainingController;
use App\Http\Controllers\ImpersonateController;
use Illuminate\Support\Facades\Route;

/*
 * Each admin section is gated by the permission that opens its page;
 * policies then check the finer actions and which users are in scope.
 */
Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::middleware('can:'.Permission::ViewUsers->value)->group(function () {
            Route::resource('users', UserController::class);
            Route::delete('users', [UserController::class, 'bulkDestroy'])->name('users.bulk-destroy');
            Route::post('users/bulk-assign-license', [UserController::class, 'bulkAssignLicense'])->name('users.bulk-assign-license');
            Route::post('users/pull-agents', [UserController::class, 'pullAgents'])->name('users.pull-agents');
            Route::get('users/pull-status/{progressId}', [UserController::class, 'pullStatus'])->name('users.pull-status');
            Route::post('users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
        });

        Route::middleware('can:'.Permission::InviteUsers->value)->group(function () {
            Route::get('invitations', [TeamInvitationController::class, 'index'])->name('invitations.index');
            Route::post('invitations', [TeamInvitationController::class, 'store'])->name('invitations.store');
            Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('invitations.destroy');
        });

        Route::middleware('can:'.Permission::ViewTrainingTracks->value)->group(function () {
            Route::get('training-tracks', [TrainingTrackController::class, 'index'])->name('training-tracks.index');
            Route::get('training-tracks/{user}', [TrainingTrackController::class, 'show'])->name('training-tracks.show');
            Route::post('training-tracks/{user}/exam-retakes', [ExamRetakeController::class, 'store'])->name('training-tracks.exam-retakes.store');
            Route::post('users/{user}/tracks/{track:id}', [UserTrackController::class, 'store'])->name('users.tracks.store')->withoutScopedBindings();
            Route::delete('users/{user}/tracks/{track:id}', [UserTrackController::class, 'destroy'])->name('users.tracks.destroy')->withoutScopedBindings();
        });

        Route::get('reports', [ReportController::class, 'index'])
            ->middleware('can:'.Permission::ViewReports->value)
            ->name('reports.index');

        Route::middleware('can:'.Permission::ViewVerticalTraining->value)->group(function () {
            Route::resource('vertical-training', VerticalTrainingController::class);
            Route::delete('vertical-training', [VerticalTrainingController::class, 'bulkDestroy'])->name('vertical-training.bulk-destroy');
        });

        Route::middleware('can:'.Permission::ViewLicensing->value)->group(function () {
            Route::resource('licensing', LicenseController::class);
            Route::delete('licensing', [LicenseController::class, 'bulkDestroy'])->name('licensing.bulk-destroy');

            Route::scopeBindings()->group(function () {
                Route::post('licensing/{licensing}/steps', [LicenseStepController::class, 'store'])->name('licensing.steps.store');
                Route::put('licensing/{licensing}/steps/{step}', [LicenseStepController::class, 'update'])->name('licensing.steps.update');
                Route::delete('licensing/{licensing}/steps/{step}', [LicenseStepController::class, 'destroy'])->name('licensing.steps.destroy');
                Route::patch('licensing/{licensing}/steps/{step}/move', [LicenseStepController::class, 'move'])->name('licensing.steps.move');
            });
        });

        Route::middleware('can:'.Permission::ViewScreening->value)->group(function () {
            Route::get('screening', [ScreeningController::class, 'index'])->name('screening.index');
            Route::post('screening', [ScreeningController::class, 'store'])->name('screening.store');
            Route::get('screening/{screening}', [ScreeningController::class, 'show'])->name('screening.show');

            Route::delete('screening-responses', [ScreeningResponseController::class, 'bulkDestroy'])->name('screening-responses.bulk-destroy');
            Route::get('screening-responses/{screeningResponse}', [ScreeningResponseController::class, 'show'])->name('screening-responses.show');
            Route::delete('screening-responses/{screeningResponse}', [ScreeningResponseController::class, 'destroy'])->name('screening-responses.destroy');

            Route::patch('call-logs/{callLog}', [CallLogController::class, 'update'])->name('call-logs.update');
        });

        Route::middleware('can:'.Permission::ManageCampaigns->value)->group(function () {
            Route::resource('campaigns', CampaignController::class)->except(['show']);
        });

        Route::middleware('can:'.Permission::ManageTeams->value)->group(function () {
            Route::resource('teams', TeamController::class)->except(['show']);
        });

        Route::middleware('can:'.Permission::ManageRoles->value)->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::put('roles/{role:name}', [RoleController::class, 'update'])->name('roles.update');
        });
    });

Route::delete('impersonate', [ImpersonateController::class, 'leave'])
    ->middleware('auth')
    ->name('impersonate.leave');
