<?php

namespace Tests\Unit;

use App\Models\DictionaryEntry;
use Flc\WordChat\Application\WordChatPromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordChatPromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }
    public function test_follow_up_prompt_includes_save_rules_reminder(): void
    {
        $builder = app(WordChatPromptBuilder::class);

        $prompt = $builder->buildFollowUpPrompt('save obviously');

        $this->assertStringContainsString('[FLC Word Chat rules for this turn]', $prompt);
        $this->assertStringContainsString('save_vocab', $prompt);
        $this->assertStringContainsString('NEVER say you cannot save words', $prompt);
        $this->assertStringContainsString('save obviously', $prompt);
    }

    public function test_system_prompt_distinguishes_personal_vocab_from_global_dictionary(): void
    {
        $builder = app(WordChatPromptBuilder::class);

        $prompt = $builder->systemPrompt();

        $this->assertStringContainsString('Personal vocabulary', $prompt);
        $this->assertStringContainsString('NEVER tell the user to tap Save in the app', $prompt);
        $this->assertStringContainsString('Global FLC dictionary', $prompt);
    }

    public function test_resolve_dictionary_for_message_returns_phonetic_and_audio(): void
    {
        \App\Models\DictionaryEntry::query()->create([
            'word' => 'obviously',
            'phonetic' => '/ˈɒb.vi.əs.li/',
            'audio_url' => 'https://example.com/obviously.mp3',
            'source' => 'seed',
            'is_curated' => false,
            'save_count' => 0,
        ]);

        $builder = app(WordChatPromptBuilder::class);
        $lookup = $builder->resolveDictionaryForMessage('What does obviously mean?');

        $this->assertIsArray($lookup);
        $this->assertSame('obviously', $lookup['word']);
        $this->assertSame('/ˈɒb.vi.əs.li/', $lookup['phonetic']);
        $this->assertSame('https://example.com/obviously.mp3', $lookup['audio_url']);
    }
}
