<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeMail;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class SocialAuthController extends Controller
{
    private const SUPPORTED_PROVIDERS = ['google', 'facebook', 'apple'];

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);

        if (! $this->isProviderConfigured($provider)) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'login_email' => __('site.auth.login.social_unavailable_error'),
                ], 'login');
        }

        $state = Str::random(40);

        $request->session()->put($this->stateSessionKey($provider), $state);

        return redirect()->away($this->authorizationUrl($provider, $state));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);

        if (! $this->isProviderConfigured($provider)) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'login_email' => __('site.auth.login.social_unavailable_error'),
                ], 'login');
        }

        if (filled($request->input('error'))) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'login_email' => __('site.auth.login.social_cancelled'),
                ], 'login');
        }

        $state = (string) $request->input('state', '');

        if (! $this->hasValidState($request, $provider, $state)) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'login_email' => __('site.auth.login.social_state_invalid'),
                ], 'login');
        }

        $code = trim((string) $request->input('code', ''));

        if ($code === '') {
            return redirect()
                ->route('login')
                ->withErrors([
                    'login_email' => __('site.auth.login.social_failed'),
                ], 'login');
        }

        try {
            $profile = match ($provider) {
                'google' => $this->fetchGoogleProfile($code),
                'facebook' => $this->fetchFacebookProfile($code),
                'apple' => $this->fetchAppleProfile($request, $code),
            };

            $user = DB::transaction(fn (): User => $this->resolveSocialUser($provider, $profile));
        } catch (RequestException|RuntimeException $exception) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'login_email' => __('site.auth.login.social_failed'),
                ], 'login');
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    private function normalizeProvider(string $provider): string
    {
        $normalized = strtolower(trim($provider));

        if (! in_array($normalized, self::SUPPORTED_PROVIDERS, true)) {
            abort(404);
        }

        return $normalized;
    }

    private function stateSessionKey(string $provider): string
    {
        return 'social_auth_state_'.$provider;
    }

    private function hasValidState(Request $request, string $provider, string $state): bool
    {
        $sessionKey = $this->stateSessionKey($provider);
        $expectedState = (string) $request->session()->pull($sessionKey, '');

        return $expectedState !== '' && hash_equals($expectedState, $state);
    }

    private function isProviderConfigured(string $provider): bool
    {
        return filled(config("services.{$provider}.client_id"))
            && filled(config("services.{$provider}.client_secret"))
            && filled(config("services.{$provider}.redirect_uri"));
    }

    private function authorizationUrl(string $provider, string $state): string
    {
        $redirectUri = (string) config("services.{$provider}.redirect_uri");
        $clientId = (string) config("services.{$provider}.client_id");

        return match ($provider) {
            'google' => 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'access_type' => 'online',
                'include_granted_scopes' => 'true',
                'prompt' => 'select_account',
                'state' => $state,
            ]),
            'facebook' => 'https://www.facebook.com/v20.0/dialog/oauth?'.http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'email,public_profile',
                'state' => $state,
            ]),
            'apple' => 'https://appleid.apple.com/auth/authorize?'.http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'response_mode' => 'form_post',
                'scope' => 'name email',
                'state' => $state,
            ]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchGoogleProfile(string $code): array
    {
        $tokenPayload = Http::asForm()
            ->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect_uri'),
                'grant_type' => 'authorization_code',
            ])
            ->throw()
            ->json();

        $userPayload = Http::withToken((string) Arr::get($tokenPayload, 'access_token'))
            ->get('https://www.googleapis.com/oauth2/v2/userinfo')
            ->throw()
            ->json();

        return [
            'provider_user_id' => (string) Arr::get($userPayload, 'id', ''),
            'email' => (string) Arr::get($userPayload, 'email', ''),
            'name' => (string) Arr::get($userPayload, 'name', ''),
            'email_verified' => (bool) Arr::get($userPayload, 'verified_email', true),
            'avatar_url' => Arr::get($userPayload, 'picture'),
            'meta' => [
                'locale' => Arr::get($userPayload, 'locale'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchFacebookProfile(string $code): array
    {
        $tokenPayload = Http::get('https://graph.facebook.com/v20.0/oauth/access_token', [
            'client_id' => config('services.facebook.client_id'),
            'client_secret' => config('services.facebook.client_secret'),
            'redirect_uri' => config('services.facebook.redirect_uri'),
            'code' => $code,
        ])->throw()->json();

        $userPayload = Http::withToken((string) Arr::get($tokenPayload, 'access_token'))
            ->get('https://graph.facebook.com/me', [
                'fields' => 'id,name,email,picture.type(large)',
            ])
            ->throw()
            ->json();

        return [
            'provider_user_id' => (string) Arr::get($userPayload, 'id', ''),
            'email' => (string) Arr::get($userPayload, 'email', ''),
            'name' => (string) Arr::get($userPayload, 'name', ''),
            'email_verified' => true,
            'avatar_url' => Arr::get($userPayload, 'picture.data.url'),
            'meta' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchAppleProfile(Request $request, string $code): array
    {
        $tokenPayload = Http::asForm()
            ->post('https://appleid.apple.com/auth/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => config('services.apple.client_id'),
                'client_secret' => config('services.apple.client_secret'),
                'redirect_uri' => config('services.apple.redirect_uri'),
            ])
            ->throw()
            ->json();

        $claims = $this->decodeJwtClaims((string) Arr::get($tokenPayload, 'id_token', ''));
        $userPayload = json_decode((string) $request->input('user', ''), true);
        $name = trim(implode(' ', array_filter([
            Arr::get($userPayload, 'name.firstName'),
            Arr::get($userPayload, 'name.lastName'),
        ])));

        if ((string) Arr::get($claims, 'iss') !== 'https://appleid.apple.com') {
            throw new RuntimeException('Invalid Apple issuer.');
        }

        if ((string) Arr::get($claims, 'aud') !== (string) config('services.apple.client_id')) {
            throw new RuntimeException('Invalid Apple audience.');
        }

        if ((int) Arr::get($claims, 'exp', 0) < now()->timestamp) {
            throw new RuntimeException('Expired Apple token.');
        }

        return [
            'provider_user_id' => (string) Arr::get($claims, 'sub', ''),
            'email' => (string) (Arr::get($claims, 'email') ?: Arr::get($userPayload, 'email', '')),
            'name' => $name,
            'email_verified' => filter_var(Arr::get($claims, 'email_verified', true), FILTER_VALIDATE_BOOL),
            'avatar_url' => null,
            'meta' => [
                'is_private_email' => filter_var(Arr::get($claims, 'is_private_email', false), FILTER_VALIDATE_BOOL),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function resolveSocialUser(string $provider, array $profile): User
    {
        $providerUserId = trim((string) ($profile['provider_user_id'] ?? ''));

        if ($providerUserId === '') {
            throw new RuntimeException('Missing provider user id.');
        }

        $socialAccount = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($socialAccount?->user instanceof User) {
            $this->updateSocialAccount($socialAccount, $profile);

            return $socialAccount->user;
        }

        $email = trim((string) ($profile['email'] ?? ''));

        if ($email === '') {
            throw new RuntimeException('Missing social account email.');
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $user = new User();
            $user->name = $this->resolveDisplayName($profile, $email);
            $user->email = $email;
            $user->password = Str::password(32);
            $user->status = 'active';
            $user->email_verified_at = ! empty($profile['email_verified']) ? now() : null;
            $user->save();

            rescue(fn () => Mail::to($user->email)->send(new WelcomeMail($user)));
        } elseif ($user->email_verified_at === null && ! empty($profile['email_verified'])) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        UserProfile::query()->updateOrCreate([
            'user_id' => $user->id,
        ], [
            'preferred_language' => app()->getLocale(),
        ]);

        $socialAccount = SocialAccount::query()->updateOrCreate([
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
        ], [
            'user_id' => $user->id,
            'provider_email' => $email,
            'provider_name' => trim((string) ($profile['name'] ?? '')) ?: $user->name,
            'avatar_url' => $profile['avatar_url'] ?? null,
            'meta' => $profile['meta'] ?? null,
        ]);

        return $socialAccount->user()->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function updateSocialAccount(SocialAccount $socialAccount, array $profile): void
    {
        $socialAccount->forceFill([
            'provider_email' => trim((string) ($profile['email'] ?? '')) ?: $socialAccount->provider_email,
            'provider_name' => trim((string) ($profile['name'] ?? '')) ?: $socialAccount->provider_name,
            'avatar_url' => $profile['avatar_url'] ?? $socialAccount->avatar_url,
            'meta' => array_merge($socialAccount->meta ?? [], $profile['meta'] ?? []),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function resolveDisplayName(array $profile, string $email): string
    {
        $providedName = trim((string) ($profile['name'] ?? ''));

        if ($providedName !== '') {
            return $providedName;
        }

        return Str::of($email)->before('@')->replace(['.', '-', '_'], ' ')->title()->toString();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtClaims(string $jwt): array
    {
        $segments = explode('.', $jwt);

        if (count($segments) < 2) {
            throw new RuntimeException('Invalid JWT payload.');
        }

        $payload = json_decode($this->base64UrlDecode($segments[1]), true);

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid JWT claims.');
        }

        return $payload;
    }

    private function base64UrlDecode(string $value): string
    {
        $padded = strtr($value, '-_', '+/');
        $padding = strlen($padded) % 4;

        if ($padding > 0) {
            $padded .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($padded, true);

        if ($decoded === false) {
            throw new RuntimeException('Unable to decode token payload.');
        }

        return $decoded;
    }
}
