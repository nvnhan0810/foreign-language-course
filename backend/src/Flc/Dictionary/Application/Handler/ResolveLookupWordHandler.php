<?php

namespace Flc\Dictionary\Application\Handler;

use Flc\Dictionary\Application\FreeDictionaryGateway;
use Flc\Dictionary\Application\LookupLemmaGenerator;
use Flc\Dictionary\Application\Query\LookupWord;
use Flc\Dictionary\Application\Query\ResolveLookupWord;
use Flc\Dictionary\Application\Repository\DictionaryEntryRepository;
use Flc\Dictionary\Application\SpellSuggestionGateway;
use Flc\Shared\Application\Config;
use Flc\Shared\Application\Query;
use Flc\Shared\Application\QueryBus;
use Flc\Shared\Application\QueryHandler;
use Flc\Shared\Support\Text;

final class ResolveLookupWordHandler implements QueryHandler
{
    public function __construct(
        private readonly DictionaryEntryRepository $entries,
        private readonly FreeDictionaryGateway $gateway,
        private readonly LookupLemmaGenerator $lemmas,
        private readonly SpellSuggestionGateway $spellSuggestions,
        private readonly QueryBus $queries,
        private readonly Config $config,
    ) {}

    public function handle(Query $query): mixed
    {
        assert($query instanceof ResolveLookupWord);

        $selected = $this->normalizeSelected($query->word);
        if ($selected === '') {
            return null;
        }

        $resolved = null;
        $method = 'exact';

        if ($this->wordExists($selected)) {
            $resolved = $selected;
            $method = 'exact';
        } else {
            foreach ($this->lemmas->candidates($selected) as $candidate) {
                if ($this->wordExists($candidate)) {
                    $resolved = $candidate;
                    $method = 'lemma_rules';
                    break;
                }
            }
        }

        if ($resolved === null && $this->datamuseEnabled()) {
            $suggested = $this->spellSuggestions->suggest($selected);

            if ($suggested !== null && $this->wordExists($suggested)) {
                $resolved = $suggested;
                $method = 'datamuse_spell';
            }
        }

        if ($resolved === null) {
            $resolved = $selected;
            $method = 'exact';
        }

        /** @var array<string, mixed>|null $dictionary */
        $dictionary = $this->queries->ask(new LookupWord($resolved));

        if ($dictionary === null && $resolved !== $selected) {
            $dictionary = $this->queries->ask(new LookupWord($selected));
            if ($dictionary !== null) {
                $resolved = $selected;
                $method = 'exact';
            }
        }

        if ($dictionary === null) {
            return null;
        }

        return [
            'selected' => $selected,
            'resolved' => (string) ($dictionary['word'] ?? $resolved),
            'method' => $method,
            'dictionary' => $dictionary,
        ];
    }

    private function normalizeSelected(string $word): string
    {
        $text = trim($word);
        $first = preg_split('/\s+/', $text)[0] ?? $text;
        $token = preg_replace('/^[^a-zA-Z]+|[^a-zA-Z\'’-]+$/u', '', $first) ?? $first;

        return Text::lower(trim($token));
    }

    private function wordExists(string $word): bool
    {
        if ($this->entries->findByWord($word) !== null) {
            return true;
        }

        return is_array($this->gateway->fetch($word));
    }

    private function datamuseEnabled(): bool
    {
        return (bool) $this->config->get('flc.lookup_resolve_enable_datamuse', true);
    }
}
