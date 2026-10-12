<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Who a Google or Facebook access token belongs to. The site signs the student in with the provider (a popup)
 * and sends the access token; the token is checked to be issued to this platform's own app before the
 * account it names is trusted. No SDK: two small HTTP calls per provider.
 */
class SocialAccounts
{
    public const PROVIDERS = ['google', 'facebook'];

    public function __construct(private PlatformSettings $settings) {}

    /**
     * The provider is set up in the settings (the Facebook check also needs the app secret).
     */
    public function enabled(string $provider): bool
    {
        return match ($provider) {
            'google' => filled($this->settings->get('google_client_id')),
            'facebook' => filled($this->settings->get('facebook_app_id')) && filled($this->settings->get('facebook_app_secret')),
            default => false,
        };
    }

    /**
     * @return array{id: string, email: string, name: string}
     *
     * @throws ValidationException when the token is not a valid one of this platform's app
     */
    public function identify(string $provider, string $token): array
    {
        $account = $provider === 'google' ? $this->google($token) : $this->facebook($token);

        if (blank($account['email'] ?? null)) {
            throw ValidationException::withMessages(['token' => 'لا يوجد بريد إلكتروني في هذا الحساب. سجّل بالبريد وكلمة المرور بدلاً منه.']);
        }

        return ['id' => (string) $account['id'], 'email' => Str::lower($account['email']), 'name' => Str::limit(trim((string) ($account['name'] ?? '')), 255, '') ?: Str::before($account['email'], '@')];
    }

    /**
     * tokeninfo says which app the token was issued to; a token of any other app is refused.
     *
     * @return array<string, mixed>
     */
    private function google(string $token): array
    {
        $info = $this->get('https://oauth2.googleapis.com/tokeninfo', ['access_token' => $token]);

        if (($info['aud'] ?? null) !== $this->settings->get('google_client_id') || ($info['email_verified'] ?? null) !== 'true' || blank($info['sub'] ?? null)) {
            throw $this->invalid();
        }

        $profile = $this->get('https://www.googleapis.com/oauth2/v3/userinfo', [], $token);

        return ['id' => $info['sub'], 'email' => $info['email'] ?? null, 'name' => $profile['name'] ?? null];
    }

    /**
     * debug_token (signed with the app's own ID and secret) says the token is valid and belongs to this app.
     *
     * @return array<string, mixed>
     */
    private function facebook(string $token): array
    {
        $appId = (string) $this->settings->get('facebook_app_id');
        $debug = $this->get('https://graph.facebook.com/debug_token', ['input_token' => $token, 'access_token' => $appId.'|'.$this->settings->get('facebook_app_secret')])['data'] ?? [];

        if (($debug['is_valid'] ?? false) !== true || (string) ($debug['app_id'] ?? '') !== $appId) {
            throw $this->invalid();
        }

        $profile = $this->get('https://graph.facebook.com/me', ['fields' => 'id,name,email', 'access_token' => $token]);

        if ((string) ($profile['id'] ?? '') !== (string) ($debug['user_id'] ?? '')) {
            throw $this->invalid();
        }

        return $profile;
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function get(string $url, array $query, ?string $bearer = null): array
    {
        try {
            $request = Http::acceptJson()->timeout(10);
            /** @var Response $response */
            $response = ($bearer ? $request->withToken($bearer) : $request)->get($url, $query);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['token' => 'تعذّر الاتصال بخدمة تسجيل الدخول. حاول مرة أخرى.']);
        }

        if ($response->failed()) {
            throw $this->invalid();
        }

        return (array) $response->json();
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages(['token' => 'انتهت جلسة تسجيل الدخول أو لم تكتمل. حاول مرة أخرى.']);
    }
}
