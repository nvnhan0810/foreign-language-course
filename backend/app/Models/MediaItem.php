<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaItem extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'url',
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
}
