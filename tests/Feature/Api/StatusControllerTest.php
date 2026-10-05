<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class StatusControllerTest extends TestCase
{
    public function test_returns_app_status_as_json(): void
    {
        $response = $this->get(route('api.status'));

        $response->assertOk()
            ->assertJsonPath('data.app', config('app.name'))
            ->assertJsonPath('data.locale', app()->getLocale())
            ->assertJsonPath('data.timezone', config('app.timezone'))
            ->assertJsonStructure(['data' => ['app', 'locale', 'timezone', 'time']]);
    }

    public function test_status_lives_under_versioned_dashboard_api_prefix(): void
    {
        $this->assertSame(url('dashboard/api/v1/status'), route('api.status'));
    }

    public function test_returns_404_as_json_for_unknown_api_route_without_accept_header(): void
    {
        $response = $this->get('/dashboard/api/v1/does-not-exist');

        $response->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message']);
    }

    public function test_returns_404_as_html_for_unknown_dashboard_page(): void
    {
        $response = $this->get('/dashboard/does-not-exist');

        $response->assertNotFound()->assertSee('الصفحة غير موجودة');
    }
}
