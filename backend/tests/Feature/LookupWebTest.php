<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_page_renders_word_chat_ui(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('user.home.lookup'))
            ->assertOk()
            ->assertSee('data-word-chat', false)
            ->assertSee('data-word-chat-agent-loading', false)
            ->assertSee('Preparing chat', false)
            ->assertSee('Ask about a word or phrase', false)
            ->assertSee('Learn', false)
            ->assertDontSee('New chat', false);
    }

    public function test_lookup_page_prefills_query_parameter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('user.home.lookup', ['q' => 'outlet']))
            ->assertOk()
            ->assertSee('outlet', false);
    }

    public function test_open_related_word_goes_to_vocab_detail_when_saved(): void
    {
        $user = User::factory()->create();
        $create = $this->actingAs($user)->postJson('/api/vocabularies', ['word' => 'happy']);
        $create->assertCreated();
        $vocabId = $create->json('data.id');

        $this->actingAs($user)
            ->get(route('user.home.word.open', ['word' => 'happy']))
            ->assertRedirect(route('user.home.vocab.show', $vocabId));
    }

    public function test_open_related_word_opens_chat_when_not_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('user.home.word.open', ['word' => 'glad']))
            ->assertRedirect(route('user.home.lookup', ['q' => 'glad']));

        $this->actingAs($user)
            ->get(route('user.home.lookup', ['q' => 'glad']))
            ->assertOk()
            ->assertSee('glad', false)
            ->assertSee('data-word-chat', false);
    }

    public function test_session_user_can_list_word_chat_messages_via_api(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/word-chat/messages')
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
