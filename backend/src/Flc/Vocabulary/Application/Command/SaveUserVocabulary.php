<?php

namespace Flc\Vocabulary\Application\Command;

use Flc\Shared\Application\Command;

final class SaveUserVocabulary implements Command
{
    /**
     * @param  list<array<string, mixed>>|null  $meanings
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $word,
        public readonly ?string $phonetic = null,
        public readonly ?array $meanings = null,
    ) {}
}
