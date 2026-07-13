<?php

namespace Flc\Dictionary\Domain\Event;

use Flc\Shared\Domain\DomainEvent;

final class DictionaryEntryCreated extends DomainEvent
{
    public static function eventType(): string
    {
        return 'dictionary.entry_created';
    }

    public static function make(
        string $word,
        ?string $phonetic,
        ?string $audioUrl,
        string $source,
        int $saveCount = 1,
    ): self {
        return new self($word, [
            'word' => $word,
            'phonetic' => $phonetic,
            'audio_url' => $audioUrl,
            'source' => $source,
            'save_count' => $saveCount,
            'is_curated' => false,
            'meanings' => [],
            'synonyms' => [],
            'antonyms' => [],
        ]);
    }
}
