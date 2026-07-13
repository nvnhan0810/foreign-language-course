<?php

namespace Flc\Dictionary\Domain\Event;

use Flc\Shared\Domain\DomainEvent;

final class DictionaryContentReplaced extends DomainEvent
{
    public static function eventType(): string
    {
        return 'dictionary.content_replaced';
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
        string $source,
        bool $isCurated,
        array $meanings,
        array $synonyms,
        array $antonyms,
    ): self {
        return new self($word, [
            'word' => $word,
            'phonetic' => $phonetic,
            'audio_url' => $audioUrl,
            'source' => $source,
            'is_curated' => $isCurated,
            'meanings' => $meanings,
            'synonyms' => $synonyms,
            'antonyms' => $antonyms,
        ]);
    }
}
