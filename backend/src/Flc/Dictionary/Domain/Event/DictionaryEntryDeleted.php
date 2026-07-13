<?php

namespace Flc\Dictionary\Domain\Event;

use Flc\Shared\Domain\DomainEvent;

final class DictionaryEntryDeleted extends DomainEvent
{
    public static function eventType(): string
    {
        return 'dictionary.entry_deleted';
    }

    public static function make(string $word): self
    {
        return new self($word, ['word' => $word]);
    }
}
