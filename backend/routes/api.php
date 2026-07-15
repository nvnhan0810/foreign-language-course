<?php

use App\Http\Controllers\Api\Agent\DictionaryController as AgentDictionaryController;
use App\Http\Controllers\Api\Agent\VocabularyController as AgentVocabularyController;
use App\Http\Controllers\Api\AgentTokenController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DictionaryController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\ListeningAssessmentController;
use App\Http\Controllers\Api\ListeningMediaController;
use App\Http\Controllers\Api\DevicePushTokenController;
use App\Http\Controllers\Api\MediaItemController;
use App\Http\Controllers\Api\NotificationSettingsController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\VocabularyController;
use App\Support\AgentToken;
use Illuminate\Support\Facades\Route;

Route::get('/config', AppConfigController::class);

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/profile', [ProfileController::class, 'show']);

    Route::post('/me/push-token', [DevicePushTokenController::class, 'store']);
    Route::delete('/me/push-token', [DevicePushTokenController::class, 'destroy']);
    Route::get('/me/notification-settings', [NotificationSettingsController::class, 'show']);
    Route::put('/me/notification-settings', [NotificationSettingsController::class, 'update']);

    Route::get('/dictionary/{word}', [DictionaryController::class, 'show']);

    Route::apiResource('vocabularies', VocabularyController::class);

    Route::middleware('agent.tokens')->prefix('me/agent-tokens')->group(function () {
        Route::get('/', [AgentTokenController::class, 'index']);
        Route::post('/', [AgentTokenController::class, 'store']);
        Route::delete('/{id}', [AgentTokenController::class, 'destroy'])->whereNumber('id');
    });

    Route::prefix('agent')->group(function () {
        Route::get('/dictionary/{word}', [AgentDictionaryController::class, 'show'])
            ->middleware('agent.ability:'.AgentToken::ABILITY_LOOKUP);
        Route::put('/dictionary/{word}', [AgentDictionaryController::class, 'curate'])
            ->middleware('agent.ability:'.AgentToken::ABILITY_CURATE);

        Route::get('/vocabularies', [AgentVocabularyController::class, 'index'])
            ->middleware('agent.ability:'.AgentToken::ABILITY_VOCAB);
        Route::post('/vocabularies', [AgentVocabularyController::class, 'store'])
            ->middleware('agent.ability:'.AgentToken::ABILITY_VOCAB);
        Route::get('/vocabularies/{vocabulary}', [AgentVocabularyController::class, 'show'])
            ->middleware('agent.ability:'.AgentToken::ABILITY_VOCAB);
        Route::put('/vocabularies/{vocabulary}', [AgentVocabularyController::class, 'update'])
            ->middleware('agent.ability:'.AgentToken::ABILITY_VOCAB);
        Route::delete('/vocabularies/{vocabulary}', [AgentVocabularyController::class, 'destroy'])
            ->middleware('agent.ability:'.AgentToken::ABILITY_VOCAB);
    });

    Route::get('/media-items/due', [MediaItemController::class, 'due']);
    Route::post('/media-items/{mediaItem}/listened', [MediaItemController::class, 'listened']);
    Route::apiResource('media-items', MediaItemController::class);

    // Listening: save YouTube/MP3 (analysis + assessments run in background on store)
    Route::prefix('listening')->group(function () {
        Route::post('/media', [ListeningMediaController::class, 'store']);
        Route::get('/media/{mediaItem}', [ListeningMediaController::class, 'show']);
        Route::put('/media/{mediaItem}/transcript', [ListeningMediaController::class, 'updateTranscript']);
        Route::get('/media/{mediaItem}/audio', [ListeningMediaController::class, 'audio']);
        Route::get('/media/{mediaItem}/assessments', [ListeningMediaController::class, 'assessments']);
        Route::get('/media/{mediaItem}/session-options', [ListeningAssessmentController::class, 'sessionOptions']);
        Route::post('/media/{mediaItem}/sessions', [ListeningAssessmentController::class, 'startSession']);

        Route::get('/assessments/{listeningAssessment}', [ListeningAssessmentController::class, 'show']);
        Route::get('/assessments/{listeningAssessment}/questions', [ListeningAssessmentController::class, 'questions']);
        Route::post('/assessments/{listeningAssessment}/attempts', [ListeningAssessmentController::class, 'submitAttempt']);
        Route::get('/assessments/{listeningAssessment}/attempts', [ListeningAssessmentController::class, 'attempts']);
    });

    Route::get('/quiz/next', [QuizController::class, 'next']);
    Route::post('/quiz/attempts', [QuizController::class, 'attempt']);

    Route::get('/sync', [SyncController::class, 'index']);
});
