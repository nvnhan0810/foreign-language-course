<?php

use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DictionaryController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\MediaItemController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\VocabularyController;
use Illuminate\Support\Facades\Route;

Route::get('/config', AppConfigController::class);

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/dictionary/{word}', [DictionaryController::class, 'show']);

    Route::apiResource('vocabularies', VocabularyController::class);

    Route::get('/media-items/due', [MediaItemController::class, 'due']);
    Route::post('/media-items/{mediaItem}/listened', [MediaItemController::class, 'listened']);
    Route::apiResource('media-items', MediaItemController::class);

    Route::get('/quiz/next', [QuizController::class, 'next']);
    Route::post('/quiz/attempts', [QuizController::class, 'attempt']);

    Route::get('/sync', [SyncController::class, 'index']);
});
