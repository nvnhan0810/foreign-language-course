<?php

namespace Flc\Dictionary\Domain\Event;

use Flc\Shared\Domain\DomainEvent;

final class DictionaryContentMerged extends DomainEvent
{
    public static function eventType(): string
    {
        return 'dictionary.content_merged';
    }

    /**
     * @param  list<array<string, mixed>>  $meanings
     * @param  list<string>  $synonyms
     * @param  list<string>  $antonyms
     */
    public static function make(
        string $word,
        ?string $phonetic,
        ?string $audioUrl,
        array $meanings,
        array $synonyms,
        array $antonyms,
        int $saveCount,
    ): self {
        return new self($word, [
            'word' => $word,
            'phonetic' => $phonetic,
            'audio_url' => $audioUrl,
            'meanings' => $meanings,
            'synonyms' => $synonyms,
            'antonyms' => $antonyms,
            'save_count' => $saveCount,
        ]);
    }
}
