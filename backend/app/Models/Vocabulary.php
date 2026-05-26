<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vocabulary extends Model
{
    protected $fillable = [
        'user_id',
        'word',
        'phonetic',
        'meanings',
        'times_quizzed',
        'last_quizzed_at',
        'last_correct_at',
    ];

    protected function casts(): array
    {
        return [
            'meanings' => 'array',
            'last_quizzed_at' => 'datetime',
            'last_correct_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function examples(): HasMany
    {
        return $this->hasMany(VocabularyExample::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
