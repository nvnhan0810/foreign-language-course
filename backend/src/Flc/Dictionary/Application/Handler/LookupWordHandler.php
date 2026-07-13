<?php

namespace Flc\Dictionary\Application\Handler;

use Flc\Dictionary\Application\FreeDictionaryGateway;
use Flc\Dictionary\Application\Query\LookupWord;
use Flc\Dictionary\Application\Repository\DictionaryEntryRepository;
use Flc\Shared\Application\Query;
use Flc\Shared\Application\QueryHandler;
use Flc\Shared\Support\Text;

final class LookupWordHandler implements QueryHandler
{
    public function __construct(
        private readonly DictionaryEntryRepository $entries,
        private readonly FreeDictionaryGateway $gateway,
    ) {}

    public function handle(Query $query): mixed
    {
        assert($query instanceof LookupWord);

        $normalized = Text::lower(trim($query->word));
        if ($normalized === '') {
            return null;
        }

        $entry = $this->entries->findByWord($normalized);
        if ($entry !== null) {
            return $entry->toClientPayload();
        }

        return $this->gateway->fetch($normalized);
    }
}
