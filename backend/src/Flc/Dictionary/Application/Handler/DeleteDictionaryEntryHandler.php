<?php

namespace Flc\Dictionary\Application\Handler;

use Flc\Dictionary\Application\Command\DeleteDictionaryEntry;
use Flc\Dictionary\Domain\DictionaryEntryAggregate;
use Flc\Shared\Application\AggregateRepository;
use Flc\Shared\Application\Command;
use Flc\Shared\Application\CommandHandler;
use Illuminate\Support\Str;

final class DeleteDictionaryEntryHandler implements CommandHandler
{
    public function __construct(
        private readonly AggregateRepository $aggregates,
    ) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof DeleteDictionaryEntry);

        $normalized = Str::lower(trim($command->word));
        /** @var DictionaryEntryAggregate|null $aggregate */
        $aggregate = $this->aggregates->load(DictionaryEntryAggregate::class, $normalized);

        if ($aggregate === null || $aggregate->isDeleted()) {
            return null;
        }

        $aggregate->delete();
        $this->aggregates->save($aggregate);

        return $aggregate;
    }
}
