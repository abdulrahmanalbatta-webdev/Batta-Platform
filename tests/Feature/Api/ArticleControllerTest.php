<?php

namespace Tests\Feature\Api;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Enums\Role;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArticleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->role(Role::Editor)->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Server Actions: متى تستخدمها',
            'excerpt' => 'مقدمة قصيرة',
            'body' => str_repeat('كلمة ', 40),
            'category' => ArticleCategory::Tutorials->value,
            'status' => ArticleStatus::Draft->value,
            'publish_at' => null,
            'is_featured' => false,
            'send_newsletter' => true,
            'meta_title' => 'Server Actions',
            'meta_description' => 'متى تستخدم Server Actions',
            ...$overrides,
        ];
    }

    public function test_lists_articles_without_bodies(): void
    {
        Article::factory()->published()->create(['body' => 'secret body text']);

        $response = $this->actingAs(User::factory()->role(Role::Support)->create())->getJson(route('api.articles.index'));

        $response->assertOk()
            ->assertJsonPath('data.0.status_label', 'منشور')
            ->assertJsonPath('data.0.category_label', 'دروس عملية')
            ->assertJsonMissingPath('data.0.body');
    }

    public function test_shows_article_with_body(): void
    {
        $article = Article::factory()->create(['body' => 'نص المقال']);

        $response = $this->actingAs($this->editor())->getJson(route('api.articles.show', $article));

        $response->assertOk()->assertJsonPath('data.body', 'نص المقال')->assertJsonPath('data.code', 'A-'.$article->id);
    }

    public function test_creates_draft_with_slug_from_latin_title_words_and_author(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->postJson(route('api.articles.store'), $this->payload());

        $response->assertCreated()->assertJsonPath('data.slug', 'server-actions')->assertJsonPath('data.status', 'draft');
        $this->assertSame($editor->id, Article::sole()->author_id);
    }

    public function test_arabic_only_title_gets_a_generated_unique_slug(): void
    {
        $this->actingAs($this->editor())->postJson(route('api.articles.store'), $this->payload(['title' => 'كيف تسعّر أول مشروع']))->assertCreated();
        $this->postJson(route('api.articles.store'), $this->payload(['title' => 'كيف تسعّر أول مشروع']))->assertCreated();

        $slugs = Article::pluck('slug');
        $this->assertCount(2, $slugs->unique());
        $this->assertTrue($slugs->every(fn (string $slug) => str_starts_with($slug, 'article-')));
    }

    public function test_repeated_latin_title_gets_numbered_slug(): void
    {
        Article::factory()->create(['slug' => 'server-actions']);

        $response = $this->actingAs($this->editor())->postJson(route('api.articles.store'), $this->payload());

        $response->assertJsonPath('data.slug', 'server-actions-2');
    }

    public function test_slug_stays_fixed_when_title_changes(): void
    {
        $article = Article::factory()->create(['slug' => 'original-slug']);

        $response = $this->actingAs($this->editor())->putJson(route('api.articles.update', $article), $this->payload(['title' => 'New Title']));

        $response->assertOk()->assertJsonPath('data.slug', 'original-slug');
    }

    public function test_publishing_sets_published_at(): void
    {
        $this->freezeSecond();

        $response = $this->actingAs($this->editor())->postJson(route('api.articles.store'), $this->payload(['status' => ArticleStatus::Published->value]));

        $response->assertCreated()->assertJsonPath('data.date', now()->toDateString());
        $this->assertEquals(now(), Article::sole()->published_at);
    }

    public function test_publishing_a_short_article_returns_422(): void
    {
        $response = $this->actingAs($this->editor())->postJson(route('api.articles.store'), $this->payload([
            'status' => ArticleStatus::Published->value,
            'body' => 'قصير جداً',
        ]));

        $response->assertUnprocessable()->assertJsonValidationErrors(['body' => 'المقال قصير جداً للنشر (أقل من 30 كلمة).']);
    }

    public function test_scheduling_needs_a_future_time(): void
    {
        $missing = $this->actingAs($this->editor())->postJson(route('api.articles.store'), $this->payload(['status' => ArticleStatus::Scheduled->value]));
        $past = $this->postJson(route('api.articles.store'), $this->payload(['status' => ArticleStatus::Scheduled->value, 'publish_at' => now()->subHour()->toIso8601String()]));

        $missing->assertUnprocessable()->assertJsonValidationErrors(['publish_at' => 'اختر موعد نشر للمقال المجدول.']);
        $past->assertUnprocessable()->assertJsonValidationErrors(['publish_at' => 'اختر موعد نشر في المستقبل للمقال المجدول.']);
    }

    public function test_schedules_article_at_the_given_instant(): void
    {
        $response = $this->actingAs($this->editor())->postJson(route('api.articles.store'), $this->payload([
            'status' => ArticleStatus::Scheduled->value,
            'publish_at' => '2030-01-15T07:00:00.000Z',
        ]));

        $response->assertCreated()->assertJsonPath('data.date', '2030-01-15');
        $this->assertSame('2030-01-15 07:00:00', Article::sole()->publish_at->utc()->toDateTimeString());
    }

    public function test_publish_time_is_dropped_when_article_is_not_scheduled(): void
    {
        $article = Article::factory()->scheduled()->create();

        $this->actingAs($this->editor())->putJson(route('api.articles.update', $article), $this->payload(['publish_at' => now()->addDay()->toIso8601String()]))->assertOk();

        $this->assertNull($article->fresh()->publish_at);
    }

    public function test_uploads_cover(): void
    {
        Storage::fake('public');
        $article = Article::factory()->create();
        $png = UploadedFile::fake()->createWithContent('cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        $response = $this->actingAs($this->editor())->postJson(route('api.articles.cover.store', $article), ['cover' => $png]);

        $response->assertOk();
        Storage::disk('public')->assertExists($article->fresh()->cover_path);
    }

    public function test_deletes_article(): void
    {
        $article = Article::factory()->create();

        $this->actingAs($this->editor())->deleteJson(route('api.articles.destroy', $article))->assertNoContent();

        $this->assertModelMissing($article);
    }

    public function test_support_cannot_create_and_gets_403(): void
    {
        $response = $this->actingAs(User::factory()->role(Role::Support)->create())->postJson(route('api.articles.store'), $this->payload());

        $response->assertForbidden();
    }
}
