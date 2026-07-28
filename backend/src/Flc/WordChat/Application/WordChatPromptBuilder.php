<?php

namespace Flc\WordChat\Application;

use Flc\Dictionary\Application\Query\LookupWord;
use Flc\Dictionary\Application\Query\ResolveLookupWord;
use Flc\Shared\Application\QueryBus;
use Flc\Shared\Support\Text;

final class WordChatPromptBuilder
{
    public function __construct(
        private readonly QueryBus $queries,
    ) {}

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
You are FLC Word Chat, an English tutor helping Vietnamese learners through English-English explanations.

Rules:
- Explain clearly with short examples when useful.
- Prefer the FLC dictionary context below when provided.
- Do not claim to update the global FLC dictionary unless the user explicitly asks to curate it.
- Keep replies focused and conversational.
- After your reply, append a fenced JSON block with quiz-ready learning insights when the turn is worth reviewing:

```json
{"insights":[{"word":"outlet","type":"usage","content":"Short summary for a quiz prompt"}]}
```

Allowed insight types: meaning, usage, context, grammar, confirmation, note.
Omit the JSON block when nothing is worth saving for review.
PROMPT;
    }

    public function buildUserPrompt(string $userText): string
    {
        $userText = trim($userText);
        $context = $this->dictionaryContext($userText);

        if ($context === null) {
            return $userText;
        }

        return $userText."\n\n---\nFLC dictionary context (JSON):\n".$context;
    }

    public function buildInitialAgentPrompt(string $userText): string
    {
        return $this->systemPrompt()."\n\n---\n\n".$this->buildUserPrompt($userText);
    }

    public function buildWarmupPrompt(): string
    {
        return $this->systemPrompt()."\n\n---\n\nWait for the user's first message about English words, phrases, usage, or grammar.";
    }

    private function dictionaryContext(string $userText): ?string
    {
        $word = $this->extractLookupWord($userText);
        if ($word === null) {
            return null;
        }

        $resolved = $this->queries->ask(new ResolveLookupWord($word));
        $dictionary = is_array($resolved['dictionary'] ?? null)
            ? $resolved['dictionary']
            : $this->queries->ask(new LookupWord($word));

        if (! is_array($dictionary)) {
            return null;
        }

        $payload = [
            'word' => $dictionary['word'] ?? $word,
            'phonetic' => $dictionary['phonetic'] ?? null,
            'meanings' => array_slice($dictionary['meanings'] ?? [], 0, 4),
            'synonyms' => array_slice($dictionary['synonyms'] ?? [], 0, 8),
            'antonyms' => array_slice($dictionary['antonyms'] ?? [], 0, 8),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $json;
    }

    private function extractLookupWord(string $text): ?string
    {
        if (preg_match('/["\']([a-z][a-z\'-]{1,48})["\']/i', $text, $matches)) {
            return Text::lower($matches[1]);
        }

        $trimmed = Text::lower(trim($text));
        if ($trimmed !== '' && preg_match('/^[a-z][a-z\'-]{0,48}$/i', $trimmed)) {
            return $trimmed;
        }

        if (preg_match('/\b([a-z][a-z\'-]{2,48})\b/i', $text, $matches)) {
            return Text::lower($matches[1]);
        }

        return null;
    }
}
