<?php

namespace Tests\Unit;

use Flc\WordChat\Application\WordChatPromptBuilder;
use Tests\TestCase;

class WordChatPromptBuilderTest extends TestCase
{
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
}
