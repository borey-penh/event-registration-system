<?php

namespace App\Http\Controllers;

use App\Models\CandidateAccount;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Candidate-facing auth (separate from manager AuthController).
 * Email + password; registering on the form creates the account,
 * so "login" doubles as self-serve sign-up on first use.
 */
class CandidateAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $account = CandidateAccount::firstOrNew(['email' => static::normalizeEmail($data['email'])]);
        $created = ! $account->exists;

        if ($created) {
            $account->password = $data['password']; // hashed via 'hashed' cast
            $account->name = $data['name'] ?? null;
            $account->save();
        } else {
            // Account exists: this must be a normal login, so verify the password.
            if ($account->password === null) {
                throw ValidationException::withMessages([
                    'password' => ['This email uses '.ucfirst($account->provider).' login. Continue with that button instead.'],
                ]);
            }
            if (! Hash::check($data['password'], $account->password)) {
                throw ValidationException::withMessages([
                    'password' => ['Incorrect password for this email.'],
                ]);
            }
        }

        return response()->json([
            'account' => ['id' => $account->id, 'email' => $account->email, 'name' => $account->name],
            'created' => $created,
            'token' => $account->createToken('candidate-flow')->plainTextToken,
        ], $created ? 201 : 200);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $account = CandidateAccount::where('email', static::normalizeEmail($data['email']))->first();

        // Social-only accounts have a null password — point at the right button
        // instead of a misleading "wrong password".
        if ($account && $account->password === null) {
            throw ValidationException::withMessages([
                'email' => ['This email uses '.ucfirst($account->provider).' login. Continue with that button instead.'],
            ]);
        }

        if (! $account || ! Hash::check($data['password'], $account->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        return response()->json([
            'account' => ['id' => $account->id, 'email' => $account->email, 'name' => $account->name],
            'token' => $account->createToken('candidate-flow')->plainTextToken,
        ]);
    }

    // ==================== Social login (Google / Facebook) ====================
    // Standard OAuth 2.0 authorization-code flow, implemented directly with
    // the HTTP client (no Socialite) to stay compatible with the SPA +
    // Sanctum-token architecture:
    //
    //   1. POST /candidate/social/redirect      → OAuth consent URL (state
    //      stashed in the cache, so the flow is CSRF-safe and replay-proof).
    //   2. GET  /candidate/social/{p}/callback  → provider returns the code;
    //      we exchange it, find-or-create the CandidateAccount and redirect
    //      back to the SPA with a ONE-TIME handoff token in the URL fragment
    //      (fragments never reach server logs or the Referer header).
    //   3. POST /candidate/social/exchange      → SPA swaps the handoff token
    //      for a regular candidate session token; the handoff is revoked.

    private const PROVIDERS = ['google', 'facebook'];

    public function socialRedirect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'in:'.implode(',', self::PROVIDERS)],
            'event_token' => ['required', 'string'],
            'origin' => ['required', 'url:http,https'],
        ]);

        $provider = $data['provider'];

        // The buttons work either through Auth0 (brokered, no own provider
        // apps needed) or with direct Google/Facebook credentials.
        if (! static::auth0Configured() && ! config("services.{$provider}.client_id")) {
            return response()->json(['message' => ucfirst($provider).' login is not configured on this server.'], 422);
        }

        // Only let the SPA bounce users back to origins this deployment
        // actually serves (prevents the callback redirect being abused).
        $origin = $this->resolveAllowedOrigin($request, $data['origin']);

        // The state is the cache key; the payload rides inside the cache. A
        // callback can consume each state exactly once (Cache::pull), which
        // covers both CSRF and token replay. The origin rides in the state so
        // the callback can rebuild the SAME redirect_uri (providers reject a
        // mismatch) and bounce the user back to where they started.
        $state = Str::random(40);
        Cache::put('social_login:'.$state, [
            'provider' => $provider,
            'event_token' => $data['event_token'],
            'origin' => $origin,
        ], now()->addMinutes(10));

        return response()->json(['redirect_url' => $this->authorizeUrl($provider, $origin, $state)]);
    }

    public function socialCallback(Request $request, string $provider): RedirectResponse
    {
        // Any failure bounces the candidate back to the registration page with
        // a readable message in the fragment — never a raw 500.
        $fail = fn (string $message) => $this->backToSpa($request, null, $message);

        try {
            // User pressed "cancel" on the consent screen.
            if ($request->filled('error')) {
                return $fail('Login was cancelled.');
            }

            // Pull the state first, so even a callback with a missing/expired
            // code knows which origin to bounce the user back to.
            $state = Cache::pull('social_login:'.(string) $request->query('state'));
            $code = (string) $request->query('code');

            if ($code === '' || ! is_array($state) || ($state['provider'] ?? null) !== $provider) {
                return $fail('Login link expired. Please try again.');
            }

            $info = $this->fetchSocialUser($provider, $code, $state['origin']);
            [$account, $created] = $this->findOrCreateSocialAccount($provider, $info);

            $handoff = $account->createToken('candidate-social');

            return $this->backToSpa(
                $request,
                $state,
                token: $handoff->plainTextToken,
                tokenId: (int) $handoff->accessToken->id,
                created: $created,
            );
        } catch (\Throwable $e) {
            report($e);

            // Deliberate aborts (no verified email, token mismatch, …) carry a
            // message worth showing; network/5xx failures get the generic one.
            $message = 'Could not complete '.ucfirst($provider).' login. Please try again.';
            if ($e instanceof HttpException && $e->getStatusCode() < 500 && filled($e->getMessage())) {
                $message = $e->getMessage();
            }

            return $fail($message);
        }
    }

    /** Swap the one-time handoff token for a regular candidate session. */
    public function socialExchange(Request $request): JsonResponse
    {
        $data = $request->validate(['token_id' => ['required', 'integer']]);

        /** @var CandidateAccount $account EnsureCandidate guarantees the type */
        $account = $request->user();

        // The handoff token must still exist (one-time use) and be fresh.
        $handoff = $account->tokens()
            ->where('id', $data['token_id'])
            ->where('name', 'candidate-social')
            ->first();

        if (! $handoff || $handoff->created_at->lt(now()->subMinutes(10))) {
            abort(403, 'This login link was already used or has expired.');
        }

        $created = $request->boolean('created');
        $token = $account->createToken('candidate-flow')->plainTextToken;
        $handoff->delete(); // burn the handoff — it can never be reused

        return response()->json([
            'account' => ['id' => $account->id, 'email' => $account->email, 'name' => $account->name],
            'created' => $created,
            'token' => $token,
        ]);
    }

    /**
     * True when AUTH0_* is fully configured. In that mode BOTH social buttons
     * route through the Auth0 tenant (which brokers Google/Facebook), so no
     * direct provider credentials are required on this server.
     */
    public static function auth0Configured(): bool
    {
        return filled(config('services.auth0.domain'))
            && filled(config('services.auth0.client_id'))
            && filled(config('services.auth0.client_secret'));
    }

    /**
     * OAuth consent-screen URL. The redirect_uri must be byte-identical
     * between the authorize step and the token step, so both derive it from
     * the same origin stashed in the state payload.
     */
    private function authorizeUrl(string $provider, string $origin, string $state): string
    {
        $redirect = $origin.'/api/candidate/social/'.$provider.'/callback';

        // Auth0-brokered mode: one tenant, one callback path per provider.
        // Auth0's Google social connection is named "google-oauth2".
        if (static::auth0Configured() && in_array($provider, ['google', 'facebook'], true)) {
            $auth0 = config('services.auth0');

            return 'https://'.$auth0['domain'].'/authorize?'.http_build_query([
                'client_id' => $auth0['client_id'],
                'redirect_uri' => $redirect,
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'state' => $state,
                'connection' => $provider === 'google' ? 'google-oauth2' : 'facebook',
            ]);
        }

        $config = config("services.{$provider}");

        if ($provider === 'google') {
            return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
                'client_id' => $config['client_id'],
                'redirect_uri' => $redirect,
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'state' => $state,
                'prompt' => 'select_account',
            ]);
        }

        return 'https://www.facebook.com/v21.0/dialog/oauth?'.http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'email public_profile',
            'state' => $state,
        ]);
    }

    /** Exchange the auth code for an access token, then load the profile. */
    private function fetchSocialUser(string $provider, string $code, string $origin): array
    {
        $redirect = $origin.'/api/candidate/social/'.$provider.'/callback';

        // Auth0-brokered mode: exchange the code at the tenant and read the
        // OIDC claims from its /userinfo endpoint.
        if (static::auth0Configured() && in_array($provider, ['google', 'facebook'], true)) {
            $auth0 = config('services.auth0');

            $token = Http::asForm()->post('https://'.$auth0['domain'].'/oauth/token', [
                'code' => $code,
                'client_id' => $auth0['client_id'],
                'client_secret' => $auth0['client_secret'],
                'redirect_uri' => $redirect,
                'grant_type' => 'authorization_code',
            ])->throw()->json();

            $info = Http::withToken($token['access_token'] ?? '')
                ->get('https://'.$auth0['domain'].'/userinfo')
                ->throw()->json();

            if (empty($info['email'])) {
                abort(422, 'Your '.ucfirst($provider).' account has no email address to log in with. Use email + password instead.');
            }

            return $info;
        }

        $config = config("services.{$provider}");

        if ($provider === 'google') {
            $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $redirect,
                'grant_type' => 'authorization_code',
            ])->throw()->json();

            $info = Http::withToken($token['access_token'] ?? '')
                ->get('https://openidconnect.googleapis.com/v1/userinfo')
                ->throw()->json();

            // Only trust addresses Google actually verified.
            if (($info['email_verified'] ?? false) !== true) {
                abort(422, 'Your Google account has no verified email address.');
            }

            return $info;
        }

        // Facebook
        $token = Http::get('https://graph.facebook.com/v21.0/oauth/access_token', [
            'code' => $code,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $redirect,
        ])->throw()->json();

        $info = Http::withToken($token['access_token'] ?? '')
            ->get('https://graph.facebook.com/v21.0/me', [
                'fields' => 'id,name,email,picture.type(large)',
            ])->throw()->json();

        // Confused-deputy check: the token must have been issued to OUR app.
        $appId = Http::withToken($token['access_token'] ?? '')
            ->get('https://graph.facebook.com/v21.0/app')
            ->throw()->json('id');

        if ((string) $appId !== (string) $config['client_id']) {
            abort(422, 'Facebook login token does not match this application.');
        }

        if (empty($info['email'])) {
            abort(422, 'Your Facebook account has no email address to log in with. Use email + password instead.');
        }

        return $info;
    }

    /**
     * Match by (provider, provider_id) first, then link an existing
     * email + password account when the provider verified the same address.
     * Otherwise create a fresh social-only account (null password). Returns
     * [account, was_created].
     */
    private function findOrCreateSocialAccount(string $provider, array $info): array
    {
        $email = filled($info['email'] ?? null) ? static::normalizeEmail((string) $info['email']) : '';
        $providerId = (string) ($info['sub'] ?? $info['id'] ?? '');
        $name = filled($info['name'] ?? null) ? trim((string) $info['name']) : null;

        // Google returns the avatar as a URL string; Facebook nests it under
        // picture.data.url.
        $picture = $info['picture'] ?? null;
        if (is_array($picture)) {
            $picture = $picture['data']['url'] ?? null;
        }
        $avatar = is_string($picture) ? $picture : null;

        $account = CandidateAccount::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        $created = false;

        if (! $account && $email !== '') {
            // OAuth provider verified this address — linking a pre-existing
            // password account to it is the standard, safe behaviour.
            $account = CandidateAccount::where('email', $email)->first();
        }

        if (! $account) {
            $account = new CandidateAccount;
            $account->email = $email !== '' ? $email : $provider.'_'.$providerId.'@social.local';
            $account->password = null; // social-only: no password login
            $created = true;
        }

        $account->provider = $provider;
        $account->provider_id = $providerId;
        $account->name = $name ?? $account->name;
        $account->avatar = is_string($avatar) ? $avatar : $account->avatar;
        $account->save();

        return [$account, $created];
    }

    /** 302 back to the SPA at the origin the flow started from. */
    private function backToSpa(
        Request $request,
        ?array $state,
        ?string $error = null,
        ?string $token = null,
        ?int $tokenId = null,
        bool $created = false,
    ): RedirectResponse {
        $origin = $state['origin']
            ?? $this->guessAllowedOrigin($request)
            ?? rtrim(config('app.url'), '/');
        $eventToken = $state['event_token'] ?? '';

        $fragment = $error !== null
            ? 'social_error='.urlencode($error)
            : http_build_query([
                'social_login' => '1',
                'token' => $token,
                'token_id' => $tokenId,
                'created' => $created ? '1' : '0',
            ]);

        return redirect()->away($origin.'/register/'.$eventToken.'#'.$fragment);
    }

    /**
     * The SPA may only bounce users to origins this deployment serves:
     * APP_URL, the public tunnel origin, or the origin the request itself
     * came from. Compares hosts so scheme/port variants keep working.
     */
    private function resolveAllowedOrigin(Request $request, string $origin): string
    {
        $allowed = array_filter([
            rtrim((string) config('app.url'), '/'),
            rtrim((string) config('app.public_origin'), '/'),
            AppInfoController::publicOrigin(),
            $request->headers->get('origin'),
        ]);

        $originHost = parse_url($origin, PHP_URL_HOST);

        foreach ($allowed as $candidate) {
            if (is_string($candidate) && parse_url($candidate, PHP_URL_HOST) === $originHost) {
                return rtrim($origin, '/');
            }
        }

        abort(403, 'This origin is not allowed to start social login.');
    }

    /** Best-effort origin from the request itself (error paths, unknown state). */
    private function guessAllowedOrigin(Request $request): ?string
    {
        $origin = $request->headers->get('origin');

        return is_string($origin) && str_starts_with($origin, 'http') ? rtrim($origin, '/') : null;
    }

    /** Trim + lowercase so "Borey@X.com" and "borey@x.com " are the same login. */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * The candidate's registration for the event this token points to.
     * Returns the form definition plus their previously saved answers so the
     * SPA can prefill the form for editing.
     */
    public function myRegistration(Request $request, string $token): JsonResponse
    {
        $event = Event::where('registration_token', $token)->firstOrFail();

        $account = $request->user();

        // Their own registration for this event — OR an anonymous submission
        // whose candidate email matches this login (e.g. filled on a phone
        // before this account existed, or on a shared device). Matching by
        // email lets the candidate see their data on ANY device.
        $registration = Registration::with('answers', 'candidate')
            ->where('event_id', $event->id)
            ->where(function ($query) use ($account) {
                $query->where('candidate_account_id', $account->id)
                    ->orWhere(function ($anonymous) use ($account) {
                        // Never touch a registration owned by ANOTHER account.
                        $anonymous->whereNull('candidate_account_id')
                            ->whereHas('candidate', fn ($c) => $c->where('email', $account->email));
                    });
            })
            // Prefer the linked registration when both somehow exist.
            ->orderByRaw('(candidate_account_id = ?) DESC', [$account->id])
            ->orderByDesc('id')
            ->first();

        // Claim the anonymous submission: future logins find it directly and
        // re-submitting updates it instead of creating a duplicate.
        if ($registration && $registration->candidate_account_id === null) {
            $registration->candidate_account_id = $account->id;
            $registration->save();
        }

        return response()->json([
            'event' => [
                'event_code' => $event->event_code,
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'start_date' => $event->start_date?->toDateString(),
                'start_time' => $event->start_time?->format('H:i'),
                'end_date' => $event->end_date?->toDateString(),
                'can_edit' => $event->canCandidateEdit(),
            ],
            'questions' => $event->questions->map(fn ($q) => [
                'id' => $q->id,
                'question' => $q->question,
                'type' => $q->type,
                'required' => $q->required,
                'options' => $q->options,
                'order' => $q->order,
            ]),
            'registration' => $registration ? [
                'id' => $registration->id,
                'qr_token' => $registration->qr_token,
                'attendance_status' => $registration->attendance_status,
                'candidate_name' => $registration->candidate?->name,
                'answers' => $registration->answers->pluck('answer', 'question_id'),
            ] : null,
        ]);
    }
}
