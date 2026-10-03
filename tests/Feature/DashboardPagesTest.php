<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function dashboardPages(): array
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
        ];
    }

    /**
     * @param  array<string, string>  $parameters
     */
    #[DataProvider('dashboardPages')]
    public function test_page_renders_for_member_with_csrf_token_api_base_and_member(string $routeName, array $parameters): void
    {
        $member = User::factory()->create(['name' => 'سارة النجار']);

        $response = $this->actingAs($member)->get(route($routeName, $parameters));

        $response->assertOk()
            ->assertSee('<meta name="csrf-token"', escape: false)
            ->assertSee('"api":'.json_encode(url('dashboard/api/v1')), escape: false)
            ->assertSee('"name":'.json_encode($member->name), escape: false);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    #[DataProvider('dashboardPages')]
    public function test_page_redirects_guest_to_login(string $routeName, array $parameters): void
    {
        $response = $this->get(route($routeName, $parameters));

        $response->assertRedirect(route('login'));
    }

    public function test_edit_page_passes_route_id_to_body(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('courses.edit', ['id' => 'C-101']));

        $response->assertSee('data-id="C-101"', escape: false);
    }

    public function test_login_page_renders_for_guest_without_member(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()->assertSee('"user":null', escape: false);
    }

    public function test_login_page_redirects_member_to_dashboard(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('login'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_reset_password_page_renders_token_and_email(): void
    {
        $response = $this->get(route('password.reset', ['token' => 'abc123', 'email' => 'sara@batta.dev']));

        $response->assertOk()
            ->assertSee('data-token="abc123"', escape: false)
            ->assertSee('value="sara@batta.dev"', escape: false)
            ->assertSee('كلمة مرور جديدة');
    }

    public function test_reset_password_page_shows_invitation_wording(): void
    {
        $response = $this->get(route('password.reset', ['token' => 'abc123', 'email' => 'sara@batta.dev', 'invite' => 1]));

        $response->assertOk()
            ->assertSee('data-invite="1"', escape: false)
            ->assertSee('قبول الدعوة');
    }
}
