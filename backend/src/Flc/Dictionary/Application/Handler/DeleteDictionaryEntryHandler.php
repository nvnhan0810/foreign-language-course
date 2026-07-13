<?php

namespace Flc\Dictionary\Application\Handler;

use Flc\Dictionary\Application\Command\DeleteDictionaryEntry;
use Flc\Dictionary\Application\Repository\DictionaryEntryRepository;
use Flc\Shared\Application\Command;
use Flc\Shared\Application\CommandHandler;
use Flc\Shared\Support\Text;

final class DeleteDictionaryEntryHandler implements CommandHandler
{
    public function __construct(
        private readonly DictionaryEntryRepository $entries,
    ) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof DeleteDictionaryEntry);

        $normalized = Text::lower(trim($command->word));
        $entry = $this->entries->findByWord($normalized);

        if ($entry === null) {
            return null;
        }

        $this->entries->deleteByWord($normalized);

        return $entry;
    }
}
