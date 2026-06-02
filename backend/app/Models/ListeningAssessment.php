<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListeningAssessment extends Model
{
    public const TYPE_QUIZ = 'quiz';

    public const TYPE_TEST = 'test';

    public const TYPE_EXAM = 'exam';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'media_item_id',
        'user_id',
        'type',
        'title',
        'description',
        'question_count',
        'time_limit_minutes',
        'status',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ListeningQuestion::class)->orderBy('order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ListeningAttempt::class);
    }
}
