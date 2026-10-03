<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionControllerTest extends TestCase
{
    use RefreshDatabase;

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
    }

    /**
     * A session last active 30 minutes ago: inside the session lifetime, so session garbage collection leaves it alone.
     */
    private function storeSession(User $member, string $id, int $idleMinutes = 30): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $member->id,
            'ip_address' => '10.0.0.2',
            'user_agent' => self::IPHONE,
            'payload' => '',
            'last_activity' => now()->subMinutes($idleMinutes)->timestamp,
        ]);
    }

    /**
     * Sign in through the real login endpoint and keep sending its session cookie, like a browser,
     * so later requests run in that same stored session.
     */
    private function signIn(User $member): void
    {
        $response = $this->postJson(route('login.store'), ['email' => $member->email, 'password' => 'password'])->assertOk();

        $cookie = config('session.cookie');
        $this->withCredentials()->withCookie($cookie, $response->getCookie($cookie)->getValue());
    }

    public function test_lists_own_devices_without_exposing_session_ids(): void
    {
        $member = User::factory()->create();
        $this->storeSession($member, 'secret-session-id');
        $this->storeSession(User::factory()->create(), 'someone-elses-session');

        $response = $this->actingAs($member)->getJson(route('api.sessions.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.device', 'iPhone · Safari')
            ->assertJsonPath('data.0.is_mobile', true)
            ->assertJsonPath('data.0.ip_address', '10.0.0.2')
            ->assertJsonPath('data.0.last_active', 'منذ 30 دقيقة')
            ->assertJsonPath('data.0.is_current', false)
            ->assertJsonPath('meta.tracked', true)
            ->assertDontSee('secret-session-id');
    }

    public function test_hides_sessions_idle_past_the_session_lifetime(): void
    {
        $member = User::factory()->create();
        $this->storeSession($member, 'expired-session', idleMinutes: config('session.lifetime') + 1);

        $response = $this->actingAs($member)->getJson(route('api.sessions.index'));

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_marks_the_current_session(): void
    {
        $member = User::factory()->create();
        $this->signIn($member);

        $response = $this->getJson(route('api.sessions.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_current', true)
            ->assertJsonPath('data.0.last_active', 'الآن');
    }

    public function test_ending_another_device_deletes_it_and_rotates_remember_token(): void
    {
        $member = User::factory()->create(['remember_token' => 'old-token']);
        $this->storeSession($member, 'other-device');
        $this->signIn($member);
        $other = collect($this->getJson(route('api.sessions.index'))->json('data'))->firstWhere('is_current', false);

        $response = $this->deleteJson(route('api.sessions.destroy', $other['id']));

        $response->assertNoContent();
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
        $this->assertNotSame('old-token', $member->fresh()->remember_token);
    }

    public function test_ending_the_current_session_returns_422(): void
    {
        $member = User::factory()->create();
        $this->signIn($member);
        $current = $this->getJson(route('api.sessions.index'))->json('data.0.id');

        $response = $this->deleteJson(route('api.sessions.destroy', $current));

        $response->assertUnprocessable()->assertJsonValidationErrors('session');
        $this->assertDatabaseCount('sessions', 1);
    }

    public function test_cannot_end_another_members_session_and_gets_404(): void
    {
        $other = User::factory()->create();
        $this->storeSession($other, 'their-device');
        $publicId = hash_hmac('sha256', 'their-device', config('app.key'));

        $response = $this->actingAs(User::factory()->create())->deleteJson(route('api.sessions.destroy', $publicId));

        $response->assertNotFound();
        $this->assertDatabaseHas('sessions', ['id' => 'their-device']);
    }

    public function test_reports_untracked_when_sessions_are_not_in_the_database(): void
    {
        config(['session.driver' => 'array']);

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.sessions.index'));

        $response->assertOk()->assertJsonPath('meta.tracked', false)->assertJsonCount(0, 'data');
    }
}
