<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\ScreeningCallController;
use App\Http\Controllers\ScreeningResponseController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\TrainingExamController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('screening/{screening:token}', [ScreeningResponseController::class, 'show'])->name('screening.show');
Route::post('screening/{screening:token}', [ScreeningResponseController::class, 'store'])->name('screening.store');

Route::post('screening/responses/{screeningResponse:token}/call/session', [ScreeningCallController::class, 'session'])->name('screening.call.session');
Route::post('screening/responses/{screeningResponse:token}/call/complete', [ScreeningCallController::class, 'complete'])->name('screening.call.complete');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('training')
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
        });

    Route::get('licenses', [LicenseController::class, 'index'])->name('licenses.index');
    Route::get('licenses/{license}', [LicenseController::class, 'show'])->name('licenses.show');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
