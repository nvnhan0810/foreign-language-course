<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DictionaryCache extends Model
{
    protected $table = 'dictionary_cache';

    public $incrementing = false;

    protected $primaryKey = 'word';

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'word',
        'payload',
        'cached_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'cached_at' => 'datetime',
        ];
    }
}
