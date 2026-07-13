<?php

namespace Flc\Dictionary\Domain\Event;

use Flc\Shared\Domain\DomainEvent;

final class DictionarySaveCounted extends DomainEvent
{
    public static function eventType(): string
    {
        return 'dictionary.save_counted';
    }

    public static function make(string $word, int $saveCount): self
    {
        return new self($word, [
            'word' => $word,
            'save_count' => $saveCount,
        ]);
    }
}
