<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaItem extends Model
{
    public const TYPE_YOUTUBE = 'youtube';

    public const TYPE_AUDIO = 'audio';

    public const TYPE_MP3 = 'mp3';

    public const ANALYSIS_PENDING = 'pending';

    public const ANALYSIS_PROCESSING = 'processing';

    public const ANALYSIS_READY = 'ready';

    public const ANALYSIS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'title',
        'url',
        'source_id',
        'audio_path',
        'audio_disk',
        'duration_seconds',
        'language',
        'analysis_status',
        'analysis_error',
        'transcript',
        'analysis_payload',
        'analyzed_at',
        'type',
        'frequency',
        'notes',
        'is_active',
        'next_listen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'next_listen_at' => 'datetime',
            'analysis_payload' => 'array',
            'analyzed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function listenLogs(): HasMany
    {
        return $this->hasMany(ListenLog::class);
    }

    public function listeningAssessments(): HasMany
    {
        return $this->hasMany(ListeningAssessment::class);
    }

    public function isAnalysisReady(): bool
    {
        return $this->analysis_status === self::ANALYSIS_READY;
    }
}
