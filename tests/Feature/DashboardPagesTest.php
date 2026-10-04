<?php

namespace Tests\Feature;

use App\Enums\LeadStage;
use App\Enums\Role;
use App\Models\Article;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\Lead;
use App\Models\Review;
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
            'workshops' => ['workshops.index', []],
            'articles' => ['articles.index', []],
            'article create' => ['articles.create', []],
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

    public function test_page_renders_sidebar_counts_for_messages_reviews_and_leads(): void
    {
        Conversation::factory()->unread()->count(2)->create();
        Conversation::factory()->create();
        Review::factory()->create();
        Review::factory()->published()->create();
        Lead::factory()->count(3)->create();
        Lead::factory()->stage(LeadStage::Won)->create();

        $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

        $response->assertOk()->assertSee('"counts":{"messages":2,"reviews":1,"leads":3}', escape: false);
    }

    public function test_course_edit_page_passes_course_id_to_body(): void
    {
        $course = Course::factory()->create();

        $response = $this->actingAs(User::factory()->create())->get(route('courses.edit', $course));

        $response->assertOk()->assertSee('data-id="'.$course->id.'"', escape: false);
    }

    public function test_article_edit_page_passes_article_id_to_body(): void
    {
        $article = Article::factory()->create();

        $response = $this->actingAs(User::factory()->create())->get(route('articles.edit', $article));

        $response->assertOk()->assertSee('data-id="'.$article->id.'"', escape: false);
    }

    public function test_edit_page_for_unknown_course_returns_404(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/dashboard/courses/999/edit');

        $response->assertNotFound();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function contentFormPages(): array
    {
        return ['course create' => ['courses.create'], 'article create' => ['articles.create']];
    }

    #[DataProvider('contentFormPages')]
    public function test_read_only_member_gets_403_on_content_forms(string $routeName): void
    {
        $response = $this->actingAs(User::factory()->role(Role::Support)->create())->get(route($routeName));

        $response->assertForbidden();
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
