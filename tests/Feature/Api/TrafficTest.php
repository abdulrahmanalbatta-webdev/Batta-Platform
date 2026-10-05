<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrafficTest extends TestCase
{
    use RefreshDatabase;

    private string $publicKey;

    /**
     * A service account key file like the one Google Cloud hands out, with a freshly made RSA key.
     */
    private function serviceAccountKey(): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $this->publicKey = openssl_pkey_get_details($key)['key'];

        return json_encode([
            'type' => 'service_account',
            'project_id' => 'batta',
            'private_key_id' => 'abc123',
            'private_key' => $privateKey,
            'client_email' => 'reader@batta.iam.gserviceaccount.com',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);
    }

    private function connect(): void
    {
        app(PlatformSettings::class)->update(['ga_property_id' => '123456789', 'ga_credentials' => $this->serviceAccountKey()]);
    }

    /**
     * @param  list<array{0: list<string>, 1: list<string|int|float>}>  $rows
     * @return array<string, mixed>
     */
    private static function report(array $rows): array
    {
        return ['rows' => array_map(fn (array $row): array => [
            'dimensionValues' => array_map(fn ($value): array => ['value' => (string) $value], $row[0]),
            'metricValues' => array_map(fn ($value): array => ['value' => (string) $value], $row[1]),
        ], $rows)];
    }

    private function fakeGoogle(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.token', 'expires_in' => 3599]),
            'analyticsdata.googleapis.com/*' => Http::response(['reports' => [
                self::report([[['date_range_0'], [1200, 1500, 4000, 0.62]], [['date_range_1'], [1000, 1400, 3500, 0.5]]]),
                self::report([[[today()->format('Ymd')], [40, 52]], [[today()->subDay()->format('Ymd')], [35, 41]]]),
                self::report([[['Organic Search'], [900]], [['Direct'], [450]], [['Organic Social'], [150]]]),
                self::report([[['mobile'], [1050]], [['desktop'], [450]]]),
                self::report([[['/courses/laravel', 'Laravel من الصفر'], [800]], [['/', '(not set)'], [600]]]),
            ]]),
        ]);
    }

    public function test_without_google_analytics_the_page_is_told_it_is_not_connected(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())->getJson(route('api.analytics.traffic'))
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('meta.configured', false);

        Http::assertNothingSent();
    }

    public function test_traffic_comes_from_the_data_api_with_a_signed_service_account_token(): void
    {
        $this->connect();
        $this->fakeGoogle();

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.analytics.traffic', ['days' => 30]));

        $response->assertOk()
            ->assertJsonPath('meta.configured', true)
            ->assertJsonPath('data.kpis.users.value', 1200)
            ->assertJsonPath('data.kpis.users.change', 20)
            ->assertJsonPath('data.kpis.engagement.value', 62)
            ->assertJsonCount(30, 'data.series.labels')
            ->assertJsonPath('data.series.users.29', 40)
            ->assertJsonPath('data.series.users.28', 35)
            ->assertJsonPath('data.series.users.0', 0)
            ->assertJsonPath('data.sources.0', ['key' => 'Organic Search', 'label' => 'بحث', 'sessions' => 900, 'value' => 60])
            ->assertJsonPath('data.devices.0.label', 'جوال')
            ->assertJsonPath('data.pages.0.title', 'Laravel من الصفر')
            ->assertJsonPath('data.pages.1.title', null);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://oauth2.googleapis.com/token') {
                return false;
            }
            [$header, $claims, $signature] = explode('.', $request['assertion']);
            $decode = fn (string $part): string => base64_decode(strtr($part, '-_', '+/'));
            $payload = json_decode($decode($claims), true);

            return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && $payload['iss'] === 'reader@batta.iam.gserviceaccount.com'
                && $payload['scope'] === 'https://www.googleapis.com/auth/analytics.readonly'
                && openssl_verify("{$header}.{$claims}", $decode($signature), $this->publicKey, OPENSSL_ALGO_SHA256) === 1;
        });
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://analyticsdata.googleapis.com/v1beta/properties/123456789:batchRunReports'
            && $request->hasHeader('Authorization', 'Bearer ya29.token')
            && count($request['requests']) === 5);
    }

    public function test_reports_and_tokens_are_cached(): void
    {
        $this->connect();
        $this->fakeGoogle();
        $member = User::factory()->create();

        $this->actingAs($member)->getJson(route('api.analytics.traffic', ['days' => 30]))->assertOk();
        $this->actingAs($member)->getJson(route('api.analytics.traffic', ['days' => 30]))->assertOk();
        $this->actingAs($member)->getJson(route('api.analytics.traffic', ['days' => 365]))->assertJsonCount(12, 'data.series.labels');

        Http::assertSentCount(3);
    }

    public function test_a_refusal_from_google_is_explained_instead_of_failing_the_page(): void
    {
        $this->connect();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.token', 'expires_in' => 3599]),
            'analyticsdata.googleapis.com/*' => Http::response(['error' => ['code' => 403]], 403),
        ]);

        $this->actingAs(User::factory()->create())->getJson(route('api.analytics.traffic'))
            ->assertOk()
            ->assertJsonPath('meta.configured', true)
            ->assertJsonPath('data', null)
            ->assertJsonPath('meta.error', fn (string $error): bool => str_contains($error, 'Viewer'));
    }

    public function test_the_connection_test_reports_success_or_the_reason(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson(route('api.settings.analytics-test'))->assertJsonValidationErrors('ga_property_id');

        $this->connect();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::sequence()
                ->push(['error' => 'invalid_grant'], 400)
                ->push(['access_token' => 'ya29.token', 'expires_in' => 3599]),
            'analyticsdata.googleapis.com/*' => Http::response(['reports' => [[]]]),
        ]);
        $this->actingAs($admin)->postJson(route('api.settings.analytics-test'))->assertJsonValidationErrors('ga_property_id');
        $this->actingAs($admin)->postJson(route('api.settings.analytics-test'))->assertOk();

        $this->actingAs(User::factory()->role(Role::Editor)->create())->postJson(route('api.settings.analytics-test'))->assertForbidden();
    }

    public function test_the_key_is_validated_owner_only_and_never_public(): void
    {
        $owner = User::factory()->owner()->create();
        $key = $this->serviceAccountKey();

        $this->actingAs($owner)->putJson(route('api.settings.update'), ['ga_credentials' => '{"type":"user"}'])
            ->assertJsonValidationErrors('ga_credentials');
        $this->actingAs(User::factory()->admin()->create())->putJson(route('api.settings.update'), ['ga_credentials' => $key])
            ->assertForbidden();

        $this->actingAs($owner)->putJson(route('api.settings.update'), ['ga_credentials' => $key, 'ga_measurement_id' => 'G-ABC123XYZ', 'ga_property_id' => '123456789'])
            ->assertOk();

        $this->actingAs($owner)->getJson(route('api.settings.show'))
            ->assertJsonPath('data.ga_credentials', ['set' => true, 'hint' => 'reader@batta.iam.gserviceaccount.com']);

        $this->getJson(route('site.settings'))
            ->assertJsonPath('data.ga_measurement_id', 'G-ABC123XYZ')
            ->assertJsonMissingPath('data.ga_credentials')
            ->assertJsonMissingPath('data.ga_property_id')
            ->assertDontSee('PRIVATE KEY');
    }
}
