<?php

namespace Flc\WordChat\Application;

use Flc\Shared\Application\CommandBus;
use Flc\Shared\Support\Text;
use Flc\Vocabulary\Application\Command\SaveUserVocabulary;
use Flc\Vocabulary\Domain\UserVocabulary;
use Flc\WordChat\Application\WordChatSaveVocabRequest;
use Throwable;

final class WordChatVocabularySaver
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly WordChatMessageRepository $messages,
    ) {}

    /**
     * @param  list<string>  $insightWords
     * @return array<string, mixed>|null
     */
    public function maybeSave(
        int $userId,
        string $userQuestion,
        ?WordChatSaveVocabRequest $saveVocabFromAgent,
        array $insightWords = [],
        ?int $beforeMessageId = null,
    ): ?array {
        $word = $saveVocabFromAgent?->word;

        if ($word === null && ($this->userRequestsSave($userQuestion) || $this->userRequestsExampleUpdate($userQuestion))) {
            $word = $this->resolveSaveWord($userQuestion, $insightWords, $userId, $beforeMessageId);
        }

        if ($word === null) {
            return null;
        }

        try {
            /** @var array{vocabulary: UserVocabulary, created: bool, backfilled: bool, content_updated?: bool}|null $result */
            $result = $this->commands->dispatch(new SaveUserVocabulary(
                userId: $userId,
                word: $word,
                phonetic: $saveVocabFromAgent?->phonetic,
                meanings: ($saveVocabFromAgent?->meanings ?? []) !== [] ? $saveVocabFromAgent->meanings : null,
                examples: ($saveVocabFromAgent?->examples ?? []) !== [] ? $saveVocabFromAgent->examples : null,
                synonyms: ($saveVocabFromAgent?->synonyms ?? []) !== [] ? $saveVocabFromAgent->synonyms : null,
                antonyms: ($saveVocabFromAgent?->antonyms ?? []) !== [] ? $saveVocabFromAgent->antonyms : null,
            ));

            if (! is_array($result)) {
                return null;
            }

            $payload = $result['vocabulary']->toApiArray();
            $payload['created'] = $result['created'];
            $payload['already_saved'] = ! $result['created'];
            if (($result['content_updated'] ?? false) === true || ($result['examples_updated'] ?? false) === true) {
                $payload['content_updated'] = true;
            }

            return $payload;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function userRequestsSave(string $text): bool
    {
        $lower = Text::lower(trim($text));

        if ($lower === '') {
            return false;
        }

        return (bool) preg_match(
            '/\b(save(?:\s+this|\s+it|\s+the\s+word|\s+word)?|bookmark|add(?:\s+to)?\s+(?:my\s+)?(?:vocab(?:ulary)?|list)|(?:to|for)\s+(?:my|your)\s+(?:vocab(?:ulary)?|list)|lưu(?:\s+từ|\s+lại|\s+giúp|\s+hộ|\s+cho\s+tôi)?|cho\s+vào\s+từ\s+vựng|ghi\s+nhớ(?:\s+từ)?)\b/u',
            $lower,
        ) || (bool) preg_match('/\b(lưu\s+từ\s+này|save\s+this\s+word)\b/u', $lower);
    }

    private function userRequestsExampleUpdate(string $text): bool
    {
        $lower = Text::lower(trim($text));
        if ($lower === '') {
            return false;
        }

        return (bool) preg_match(
            '/\b(update|add|save|append|put).*(example|examples|sentence|sentences|câu\s+ví\s+dụ|ví\s+dụ)|\b(example|examples|sentence|sentences|câu\s+ví\s+dụ|ví\s+dụ).*(update|add|save|vocab(?:ulary)?|từ\s+vựng)|\b(cập\s+nhật|thêm).*(ví\s+dụ|câu\s+ví\s+dụ|example|examples)\b/u',
            $lower,
        );
    }

    /**
     * @param  list<string>  $insightWords
     */
    private function resolveSaveWord(
        string $userQuestion,
        array $insightWords,
        int $userId,
        ?int $beforeMessageId,
    ): ?string {
        $quoted = $this->extractQuotedWord($userQuestion);
        if ($quoted !== null) {
            return $quoted;
        }

        if (preg_match('/\b(?:save|lưu(?:\s+từ)?|bookmark|add)\s+(?:the\s+word\s+)?([a-z][a-z\'-]{1,48})\b/i', $userQuestion, $matches) === 1) {
            return Text::lower($matches[1]);
        }

        foreach ($insightWords as $word) {
            $normalized = $this->normalizeWord($word);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        foreach ($this->recentContextWords($userId, $beforeMessageId) as $word) {
            $normalized = $this->normalizeWord($word);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $this->extractLookupWord($userQuestion);
    }

    /**
     * @return list<string>
     */
    private function recentContextWords(int $userId, ?int $beforeMessageId): array
    {
        $messages = $this->messages->listForUser($userId, $beforeMessageId, 12);
        $words = [];

        foreach (array_reverse($messages) as $message) {
            $word = $this->extractLookupWord($message->content);
            if ($word !== null) {
                $words[] = $word;
            }
        }

        return $words;
    }

    private function normalizeWord(?string $word): ?string
    {
        $word = Text::lower(trim((string) $word));

        return $word !== '' ? $word : null;
    }

    private function extractQuotedWord(string $text): ?string
    {
        if (preg_match('/["\']([a-z][a-z\'-]{1,48})["\']/i', $text, $matches) === 1) {
            return Text::lower($matches[1]);
        }

        return null;
    }

    private function extractLookupWord(string $text): ?string
    {
        if (preg_match('/\b(?:what\s+does|meaning\s+of|define|explain)\s+["\']?([a-z][a-z\'-]{1,48})["\']?\b/i', $text, $matches) === 1) {
            return Text::lower($matches[1]);
        }

        $trimmed = Text::lower(trim($text));
        if ($trimmed !== '' && preg_match('/^[a-z][a-z\'-]{0,48}$/i', $trimmed)) {
            return $trimmed;
        }

        if (preg_match('/\b([a-z][a-z\'-]{2,48})\b/i', $text, $matches) === 1) {
            return Text::lower($matches[1]);
        }

        return null;
    }
}
