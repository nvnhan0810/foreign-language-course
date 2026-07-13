<?php

namespace Flc\Shared\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

class EventStoreRecord extends Model
{
    protected $table = 'event_store';

    public $timestamps = false;

    protected $fillable = [
        'aggregate_type',
        'aggregate_id',
        'playhead',
        'event_type',
        'payload',
        'metadata',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
            'recorded_at' => 'datetime',
            'playhead' => 'integer',
        ];
    }
}
