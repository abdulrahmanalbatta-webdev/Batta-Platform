<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_send_a_csp_whose_nonce_matches_the_inline_script(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([A-Za-z0-9]{32})'/", $csp);
        preg_match("/'nonce-([A-Za-z0-9]{32})'/", $csp, $nonce);
        $response->assertSee('<script nonce="'.$nonce[1].'">', escape: false)
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_pages_have_no_inline_event_handlers(): void
    {
        $member = User::factory()->create();

        foreach (['dashboard', 'settings.index', 'profile', 'login'] as $route) {
            $html = $route === 'login' ? $this->get(route($route))->getContent() : $this->actingAs($member)->get(route($route))->getContent();
            $this->assertDoesNotMatchRegularExpression('/\son(click|error|load|submit|change)=/i', $html, $route);
        }
    }

    public function test_api_responses_carry_the_headers_too(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('api.status'))->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_the_dashboard_api_has_an_overall_limit_per_member(): void
    {
        $member = User::factory()->create();

        $response = $this->actingAs($member)->getJson(route('api.status'));

        $response->assertHeader('X-RateLimit-Limit', 300);
    }
}
