<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebviewSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_mint_and_redeem_handoff_creates_web_session(): void
    {
        $user = User::factory()->create([
            'email' => 'webview@example.com',
        ]);
        Sanctum::actingAs($user, ['*']);

        $mint = $this->postJson('/api/auth/webview-session', [
            'next' => '/home/vocab',
        ]);

        $mint->assertOk()
            ->assertJsonStructure(['handoff_url', 'expires_in']);

        $handoffUrl = $mint->json('handoff_url');
        $this->assertIsString($handoffUrl);
        $this->assertStringContainsString('/auth/webview-handoff', $handoffUrl);
        $this->assertStringContainsString('flc_app=1', $handoffUrl);

        $query = parse_url($handoffUrl, PHP_URL_QUERY);
        parse_str((string) $query, $params);
        $code = $params['code'] ?? '';
        $this->assertNotSame('', $code);
        $this->assertTrue(Cache::has('flc_webview_handoff:'.$code));

        $redeem = $this->get('/auth/webview-handoff?'.http_build_query([
            'code' => $code,
            'flc_app' => '1',
        ]));

        $redeem->assertRedirect('/home/vocab');
        $this->assertAuthenticatedAs($user);
        $this->assertFalse(Cache::has('flc_webview_handoff:'.$code));
    }

    public function test_handoff_code_is_single_use(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $mint = $this->postJson('/api/auth/webview-session');
        $mint->assertOk();

        parse_str((string) parse_url($mint->json('handoff_url'), PHP_URL_QUERY), $params);
        $code = $params['code'];

        $this->get('/auth/webview-handoff?code='.$code)
            ->assertRedirect(route('user.home.lookup'));
        $this->assertAuthenticatedAs($user);

        // New browser session cannot reuse the spent code.
        $this->flushSession();
        $this->assertGuest();

        $this->get('/auth/webview-handoff?code='.$code)
            ->assertRedirect(route('user.login'));
        $this->assertGuest();
    }

    public function test_scoped_token_cannot_mint_webview_session(): void
    {
        $user = User::factory()->create();
        $scoped = $user->createToken('scoped-token', ['agent:lookup']);

        $this->withToken($scoped->plainTextToken)
            ->postJson('/api/auth/webview-session')
            ->assertForbidden();
    }

    public function test_rejects_open_redirect_next_path(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $mint = $this->postJson('/api/auth/webview-session', [
            'next' => 'https://evil.example/phish',
        ]);
        $mint->assertOk();

        parse_str((string) parse_url($mint->json('handoff_url'), PHP_URL_QUERY), $params);
        $this->get('/auth/webview-handoff?code='.$params['code'])
            ->assertRedirect(route('user.home.lookup'));
    }
}
