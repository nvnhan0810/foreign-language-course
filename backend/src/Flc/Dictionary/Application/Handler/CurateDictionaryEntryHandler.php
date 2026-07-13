<?php

namespace Flc\Dictionary\Application\Handler;

use Flc\Dictionary\Application\Command\CurateDictionaryEntry;
use Flc\Dictionary\Domain\DictionaryEntryAggregate;
use Flc\Shared\Application\AggregateRepository;
use Flc\Shared\Application\Command;
use Flc\Shared\Application\CommandHandler;
use Illuminate\Support\Str;

final class CurateDictionaryEntryHandler implements CommandHandler
{
    public function __construct(
        private readonly AggregateRepository $aggregates,
    ) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof CurateDictionaryEntry);

        $normalized = Str::lower(trim($command->word));

        /** @var DictionaryEntryAggregate|null $aggregate */
        $aggregate = $this->aggregates->load(DictionaryEntryAggregate::class, $normalized);

        if ($aggregate === null || $aggregate->isDeleted()) {
            $aggregate = DictionaryEntryAggregate::createFromPayload($normalized, [
                'phonetic' => $command->data['phonetic'] ?? null,
                'audio_url' => $command->data['audio_url'] ?? null,
                'source' => 'admin',
                'meanings' => [],
                'synonyms' => [],
                'antonyms' => [],
            ]);
            $this->aggregates->save($aggregate);
            $aggregate = $this->aggregates->load(DictionaryEntryAggregate::class, $normalized);
        }

        assert($aggregate instanceof DictionaryEntryAggregate);
        $aggregate->curate(array_merge($command->data, ['word' => $normalized]));
        $this->aggregates->save($aggregate);

        return $aggregate;
    }
}
