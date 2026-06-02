<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AllowedEmailController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ListeningAssessmentController;
use App\Http\Controllers\Admin\MediaItemController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VocabularyController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('admin.login'));

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::get('auth/google', [AdminAuthController::class, 'redirectGoogle'])->name('auth.google');
    Route::get('auth/google/callback', [AdminAuthController::class, 'callbackGoogle'])->name('auth.google.callback');

    Route::middleware('admin')->group(function () {
        Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('allowed-emails', AllowedEmailController::class)->except(['show']);
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

        Route::resource('users', UserController::class)->only(['index', 'show', 'destroy']);
        Route::resource('vocabularies', VocabularyController::class)->only(['index', 'edit', 'update', 'destroy']);

        Route::post('media-items/{mediaItem}/process', [MediaItemController::class, 'process'])->name('media-items.process');
        Route::post('media-items/{mediaItem}/regenerate-assessments', [MediaItemController::class, 'regenerateAssessments'])->name('media-items.regenerate-assessments');
        Route::resource('media-items', MediaItemController::class);

        Route::post('listening-assessments/{listeningAssessment}/regenerate', [ListeningAssessmentController::class, 'regenerate'])->name('listening-assessments.regenerate');
        Route::resource('listening-assessments', ListeningAssessmentController::class)->only(['index', 'show', 'destroy']);
    });
});
