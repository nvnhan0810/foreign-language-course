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
- Keep replies focused and conversational.

Personal vocabulary (bookmarking words for the user):
- When the user wants to save, bookmark, or add a word to THEIR personal vocabulary (e.g. "save", "save this word", "add to my list", "lưu từ", "lưu từ này"), confirm briefly in your reply.
- FLC saves personal vocabulary server-side from your JSON block — include save_vocab when the user wants the word kept.
- NEVER say you cannot save words from chat, NEVER tell the user to tap Save in the app, and NEVER mention the global FLC dictionary in a save reply.
- Personal vocabulary saves are NOT the same as editing the global FLC dictionary.

Global FLC dictionary (admin curation only):
- Only mention curating the global dictionary when the user explicitly asks to edit or curate FLC's shared dictionary entries.

After your reply, append a fenced JSON block when insights or vocabulary save apply:

```json
{"insights":[{"word":"outlet","type":"usage","content":"Short summary for a quiz prompt"}],"save_vocab":{"word":"outlet"}}
```

Allowed insight types: meaning, usage, context, grammar, confirmation, note.
Include save_vocab when the user clearly wants the word in their personal vocabulary; use the exact headword being discussed.
Omit the JSON block when nothing is worth saving for review and no vocabulary save was requested.
PROMPT;
    }

    public function followUpRulesReminder(): string
    {
        return <<<'REMINDER'
[FLC Word Chat rules for this turn]
- Personal vocabulary: when the user wants to save/bookmark/add a word to THEIR list, confirm briefly and include `"save_vocab":{"word":"..."}` in the JSON block. FLC saves it server-side automatically.
- NEVER say you cannot save words, never say "tap Save in the app", and never mention the global FLC dictionary unless the user explicitly asks to curate it.
- Saving to personal vocabulary is NOT the same as editing the global dictionary.

REMINDER;
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

    public function buildFollowUpPrompt(string $userText): string
    {
        return $this->followUpRulesReminder().$this->buildUserPrompt($userText);
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
