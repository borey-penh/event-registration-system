<?php

namespace Tests\Feature;

use App\Models\CandidateAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CandidateSocialLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_returns_provider_consent_url(): void
    {
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'secret');

        $response = $this->postJson('/api/candidate/social/redirect', [
            'provider' => 'google',
            'event_token' => 'EVT123',
            'origin' => 'http://localhost:5173',
        ], ['Origin' => 'http://localhost:5173']);

        $response->assertOk()
            ->assertJsonStructure(['redirect_url']);

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $response->json('redirect_url'));
        $this->assertStringContainsString('client_id=test-client-id', $response->json('redirect_url'));
    }

    public function test_redirect_rejects_unlisted_provider(): void
    {
        $this->postJson('/api/candidate/social/redirect', [
            'provider' => 'twitter',
            'event_token' => 'EVT123',
            'origin' => 'http://localhost:5173',
        ])->assertStatus(422);
    }

    public function test_redirect_rejects_foreign_origin(): void
    {
        config()->set('services.google.client_id', 'test-client-id');

        $this->postJson('/api/candidate/social/redirect', [
            'provider' => 'google',
            'event_token' => 'EVT123',
            'origin' => 'https://evil.example.com',
        ])->assertStatus(403);
    }

    public function test_redirect_fails_when_provider_not_configured(): void
    // phpcs:ignore
    {
        $this->postJson('/api/candidate/social/redirect', [
            'provider' => 'facebook',
            'event_token' => 'EVT123',
            'origin' => 'http://localhost:5173',
        ], ['Origin' => 'http://localhost:5173'])->assertStatus(422);
    }

    public function test_callback_creates_account_and_redirects_with_handoff(): void
    {
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.redirect', 'http://localhost/api/candidate/social/google/callback');

        $state = Str::random(40);
        Cache::put('social_login:'.$state, [
            'provider' => 'google',
            'event_token' => 'EVT123',
            'origin' => 'http://localhost:5173',
        ], now()->addMinutes(10));

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'ga-tok']),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-sub-1',
                'email' => 'New.Candidate@Example.com ',
                'email_verified' => true,
                'name' => 'Test Candidate',
                'picture' => 'https://lh3.googleusercontent.com/a.jpg',
            ]),
        ]);

        $response = $this->get('/api/candidate/social/google/callback?code=abc&state='.$state);

        $response->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('http://localhost:5173/register/EVT123#', $location);
        $this->assertStringContainsString('social_login=1', $location);

        // Account created, email normalized, password null, avatar stored.
        $account = CandidateAccount::where('provider', 'google')->first();
        $this->assertNotNull($account);
        $this->assertSame('new.candidate@example.com', $account->email);
        $this->assertNull($account->password);
        $this->assertSame('Test Candidate', $account->name);
        $this->assertSame('https://lh3.googleusercontent.com/a.jpg', $account->avatar);

        // The handoff token in the fragment matches a real Sanctum token.
        parse_str(parse_url($location, PHP_URL_FRAGMENT), $fragment);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => CandidateAccount::class,
            'tokenable_id' => $account->id,
            'name' => 'candidate-social',
        ]);
        $this->assertSame($fragment['token_id'], (string) $account->tokens()->where('name', 'candidate-social')->first()->id);
    }

    public function test_callback_links_existing_email_account(): void
    {
        config()->set('services.google.client_id', 'test-client-id');

        $existing = CandidateAccount::create([
            'email' => 'borey@example.com',
            'password' => 'secret123',
            'name' => 'Borey',
        ]);

        $state = Str::random(40);
        Cache::put('social_login:'.$state, [
            'provider' => 'google',
            'event_token' => 'EVT123',
            'origin' => 'http://localhost:5173',
        ], now()->addMinutes(10));

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'ga-tok']),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-sub-2',
                'email' => 'BOREY@example.com',
                'email_verified' => true,
                'name' => 'Borey (Google)',
            ]),
        ]);

        $this->get('/api/candidate/social/google/callback?code=abc&state='.$state)->assertRedirect();

        $existing->refresh();
        $this->assertSame('google', $existing->provider);
        $this->assertSame('google-sub-2', $existing->provider_id);
        $this->assertSame('Borey (Google)', $existing->name);
        $this->assertSame('borey@example.com', $existing->email);
        $this->assertCount(1, CandidateAccount::all());
    }

    public function test_callback_rejects_replayed_state(): void
    {
        config()->set('services.google.client_id', 'test-client-id');

        $state = Str::random(40);
        Cache::put('social_login:'.$state, [
            'provider' => 'google',
            'event_token' => 'EVT123',
            'origin' => 'http://localhost:5173',
        ], now()->addMinutes(10));

        // First call consumes it, second must fail even with a valid code.
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'ga-tok']),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'sub', 'email' => 'a@b.com', 'email_verified' => true,
            ]),
        ]);

        $this->get('/api/candidate/social/google/callback?code=abc&state='.$state)->assertRedirect();

        $second = $this->get('/api/candidate/social/google/callback?code=abc&state='.$state);
        $second->assertRedirect();

        $this->assertStringContainsString('social_error=', (string) parse_url($second->headers->get('Location'), PHP_URL_FRAGMENT));
    }

    public function test_exchange_swaps_handoff_for_session_token(): void
    {
        $account = CandidateAccount::create([
            'email' => 'swap@example.com',
            'password' => 'secret123',
        ]);

        $handoff = $account->createToken('candidate-social');

        $response = $this->postJson('/api/candidate/social/exchange', [
            'token_id' => $handoff->accessToken->id,
        ], ['Authorization' => 'Bearer '.$handoff->plainTextToken]);

        $response->assertOk()
            ->assertJsonPath('account.email', 'swap@example.com')
            ->assertJsonPath('created', false);

        // Handoff burned, session token issued.
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $handoff->accessToken->id]);
        $this->assertNotNull($response->json('token'));

        $sessionToken = $response->json('token');
        $this->get('/api/candidate/my/registration/EVT', ['Authorization' => 'Bearer '.$sessionToken])
            ->assertStatus(404); // auth passes (not 401), event simply doesn't exist
    }

    public function test_exchange_rejects_reused_handoff(): void
    {
        $account = CandidateAccount::create([
            'email' => 'reuse@example.com',
            'password' => 'secret123',
        ]);

        $handoff = $account->createToken('candidate-social');
        $headers = ['Authorization' => 'Bearer '.$handoff->plainTextToken];

        $this->postJson('/api/candidate/social/exchange', ['token_id' => $handoff->accessToken->id], $headers)->assertOk();

        // Within one test process the Sanctum RequestGuard caches the resolved
        // user, so forget the guards to simulate a genuinely fresh request
        // (production always is one). The handoff is burned → unauthenticated.
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/candidate/social/exchange', ['token_id' => $handoff->accessToken->id], $headers)
            ->assertStatus(401);
    }

    public function test_password_login_blocks_social_only_account(): void
    {
        CandidateAccount::create([
            'email' => 'social@example.com',
            'password' => null,
            'provider' => 'facebook',
            'provider_id' => 'fb-1',
        ]);

        $this->postJson('/api/candidate/login', [
            'email' => 'social@example.com',
            'password' => 'whatever',
        ])->assertStatus(422)
            ->assertJsonFragment(['This email uses Facebook login. Continue with that button instead.']);
    }
}
