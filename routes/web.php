<?php

use App\Enums\Permission;
use App\Enums\RoleplayVertical;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\RoleplayCallController;
use App\Http\Controllers\RoleplayController;
use App\Http\Controllers\ScreeningCallController;
use App\Http\Controllers\ScreeningResponseController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\TrainingExamController;
use App\Http\Controllers\TrainingModuleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('screening/{screening:token}', [ScreeningResponseController::class, 'show'])->name('screening.show');
Route::post('screening/{screening:token}', [ScreeningResponseController::class, 'store'])->name('screening.store');

Route::post('screening/responses/{screeningResponse:token}/call/session', [ScreeningCallController::class, 'session'])->name('screening.call.session');
Route::post('screening/responses/{screeningResponse:token}/call/complete', [ScreeningCallController::class, 'complete'])->name('screening.call.complete');

Route::middleware('guest')->group(function () {
    Route::get('invite/{invitation:token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('invite/{invitation:token}', [InvitationController::class, 'store'])->middleware('throttle:10,1')->name('invitations.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->middleware('can:'.Permission::ViewDashboard->value)->name('dashboard');

    Route::prefix('training')
        ->middleware('can:'.Permission::ViewTraining->value)
        ->name('training.')
        ->scopeBindings()
        ->group(function () {
            Route::get('/', [TrainingController::class, 'index'])->name('index');
            Route::get('{track}', [TrainingController::class, 'showTrack'])->name('tracks.show');
            Route::get('{track}/exam', [TrainingExamController::class, 'show'])->name('exam.show');
            Route::post('{track}/exam/{section}', [TrainingExamController::class, 'start'])->name('exam.start');
            Route::get('{track}/exam/{section}', [TrainingExamController::class, 'take'])->name('exam.take');
            Route::post('{track}/exam/{section}/submit', [TrainingExamController::class, 'submit'])->name('exam.submit');
            Route::get('{track}/{module}', [TrainingController::class, 'showModule'])->name('modules.show');
            Route::get('{track}/{module}/lessons/{lesson}', [TrainingController::class, 'showLesson'])->name('lessons.show');
            Route::post('{track}/{module}/lessons/{lesson}/complete', [TrainingController::class, 'completeLesson'])->name('lessons.complete');
            Route::get('{track}/{module}/quiz', [TrainingController::class, 'showQuiz'])->name('quiz.show');
            Route::post('{track}/{module}/quiz', [TrainingController::class, 'submitQuiz'])->name('quiz.store');
            Route::get('{track}/{module}/roleplay', [TrainingController::class, 'showRoleplay'])->name('roleplay.show');
            Route::post('{track}/{module}/roleplay', [TrainingController::class, 'startRoleplay'])->middleware('throttle:roleplay-sessions')->name('roleplay.store');
            Route::get('{track}/{module}/roleplay/attempts/{roleplaySession}', [TrainingController::class, 'showRoleplayAttempt'])->name('roleplay.attempts.show');
        });

    Route::prefix('training-modules')
        ->middleware('can:'.Permission::ViewTrainingModules->value)
        ->name('training-modules.')
        ->scopeBindings()
        ->group(function () {
            Route::get('/', [TrainingModuleController::class, 'index'])->name('index');
            Route::get('{track}', [TrainingModuleController::class, 'showTrack'])->name('tracks.show');
            Route::get('{track}/{module}', [TrainingModuleController::class, 'showModule'])->name('modules.show');
            Route::get('{track}/{module}/lessons/{lesson}', [TrainingModuleController::class, 'showLesson'])->name('lessons.show');
            Route::get('{track}/{module}/quiz', [TrainingModuleController::class, 'showQuiz'])->name('quiz.show');
        });

    foreach (RoleplayVertical::cases() as $vertical) {
        Route::prefix($vertical->routePrefix())->name($vertical->routePrefix().'.')->middleware('can:'.Permission::forRoleplay($vertical)->value)->group(function () use ($vertical) {
            Route::get('/', [RoleplayController::class, 'index'])->defaults('vertical', $vertical->value)->name('index');
            Route::post('sessions', [RoleplayController::class, 'store'])->defaults('vertical', $vertical->value)->middleware('throttle:roleplay-sessions')->name('sessions.store');
            Route::get('sessions/{roleplaySession}', [RoleplayController::class, 'show'])->defaults('vertical', $vertical->value)->name('sessions.show');
            Route::get('sessions/{roleplaySession}/recording', [RoleplayController::class, 'recording'])->defaults('vertical', $vertical->value)->name('sessions.recording');
            Route::post('sessions/{roleplaySession}/call', [RoleplayCallController::class, 'session'])->defaults('vertical', $vertical->value)->name('call.session');
            Route::post('sessions/{roleplaySession}/call/complete', [RoleplayCallController::class, 'complete'])->defaults('vertical', $vertical->value)->name('call.complete');
        });
    }

    Route::middleware('can:'.Permission::ViewLicenses->value)->group(function () {
        Route::get('licenses', [LicenseController::class, 'index'])->name('licenses.index');
        Route::get('licenses/{license}', [LicenseController::class, 'show'])->name('licenses.show');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
