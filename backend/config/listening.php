<?php

return [

    'cursor_api_key' => env('CURSOR_API_KEY'),

    'cursor_api_base' => env('CURSOR_API_BASE', 'https://api.cursor.com'),

    'cursor_model' => env('CURSOR_MODEL', 'composer-2.5'),

    'cursor_timeout_seconds' => (int) env('CURSOR_TIMEOUT_SECONDS', 180),

    'cursor_poll_interval_seconds' => (int) env('CURSOR_POLL_INTERVAL_SECONDS', 3),

    /*
    |--------------------------------------------------------------------------
    | Assessment defaults (question counts & time limits)
    |--------------------------------------------------------------------------
    */
    'assessments' => [
        'quiz' => [
            'question_count' => 5,
            'time_limit_minutes' => 10,
            'title_suffix' => 'Quick Quiz',
        ],
        'test' => [
            'question_count' => 10,
            'time_limit_minutes' => 20,
            'title_suffix' => 'Listening Test',
        ],
        'exam' => [
            'question_count' => 20,
            'time_limit_minutes' => 45,
            'title_suffix' => 'Listening Exam',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploaded audio constraints
    |--------------------------------------------------------------------------
    */
    'max_audio_size_mb' => (int) env('FLC_MAX_AUDIO_SIZE_MB', 50),

    'allowed_audio_mimes' => [
        'audio/mpeg',
        'audio/mp3',
        'audio/wav',
        'audio/x-wav',
        'audio/mp4',
        'audio/x-m4a',
        'audio/m4a',
        'video/mp4',
    ],

];
