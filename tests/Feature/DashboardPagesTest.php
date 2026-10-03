<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardPagesTest extends TestCase
{
    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function pages(): array
    {
        return [
            'dashboard' => ['dashboard', []],
            'analytics' => ['analytics', []],
            'courses' => ['courses.index', []],
            'course create' => ['courses.create', []],
            'course edit' => ['courses.edit', ['id' => 'C-101']],
            'workshops' => ['workshops.index', []],
            'articles' => ['articles.index', []],
            'article create' => ['articles.create', []],
            'article edit' => ['articles.edit', ['id' => 'A-1']],
            'tools' => ['tools.index', []],
            'orders' => ['orders.index', []],
            'coupons' => ['coupons.index', []],
            'leads' => ['leads.index', []],
            'students' => ['students.index', []],
            'messages' => ['messages.index', []],
            'reviews' => ['reviews.index', []],
            'settings' => ['settings.index', []],
            'profile' => ['profile', []],
            'login' => ['login', []],
        ];
    }

    /**
     * @param  array<string, string>  $parameters
     */
    #[DataProvider('pages')]
    public function test_page_renders_with_csrf_token_and_api_base(string $routeName, array $parameters): void
    {
        $response = $this->get(route($routeName, $parameters));

        $response->assertOk()
            ->assertSee('<meta name="csrf-token"', escape: false)
            ->assertSee('"api":'.json_encode(url('dashboard/api/v1')), escape: false);
    }

    public function test_edit_page_passes_route_id_to_body(): void
    {
        $response = $this->get(route('courses.edit', ['id' => 'C-101']));

        $response->assertSee('data-id="C-101"', escape: false);
    }
}
