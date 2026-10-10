<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Article;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningPathControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // the migration brings the site's starting paths along; these tests start from an empty list
        LearningPath::query()->delete();
    }

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
            'title' => 'مطوّر واجهات Frontend',
            'icon' => 'monitor',
            'summary' => 'من أول سطر HTML إلى تطبيق كامل.',
            'audience' => 'للمبتدئين',
            'duration' => '6–9 أشهر',
            'outcomes' => ['بناء صفحات متجاوبة'],
            'is_published' => true,
            'stages' => [
                [
                    'title' => 'أساسيات الويب',
                    'text' => 'HTML و CSS.',
                    'topics' => ['HTML', 'Flexbox'],
                    'resources' => [['title' => 'MDN', 'url' => 'https://developer.mozilla.org', 'type' => 'docs', 'lang' => 'en', 'stray' => 'x']],
                    'items' => [],
                ],
            ],
            ...$overrides,
        ];
    }

    public function test_an_editor_builds_a_path_with_stages_resources_and_linked_content(): void
    {
        $course = Course::factory()->published()->create();
        $workshop = Workshop::factory()->create();
        $article = Article::factory()->published()->create();
        LearningPath::factory()->create(['position' => 4]);

        $stages = $this->payload()['stages'];
        $stages[0]['items'] = [['type' => 'course', 'id' => $course->id], ['type' => 'workshop', 'id' => $workshop->id], ['type' => 'article', 'id' => $article->id], ['type' => 'course', 'id' => $course->id]];

        $response = $this->actingAs($this->editor())->postJson(route('api.learning-paths.store'), $this->payload(['stages' => $stages]));

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'frontend')
            ->assertJsonPath('data.stages_count', 1)
            ->assertJsonPath('data.resources_count', 1)
            ->assertJsonPath('data.items_count', 3)
            ->assertJsonMissingPath('data.stages.0.resources.0.stray');

        $path = LearningPath::query()->where('slug', 'frontend')->sole();
        $this->assertSame(5, $path->position);
        $this->assertEquals([['type' => 'course', 'id' => $course->id], ['type' => 'workshop', 'id' => $workshop->id], ['type' => 'article', 'id' => $article->id]], $path->stages[0]['items']);
    }

    public function test_the_map_is_validated_down_to_each_resource_and_link(): void
    {
        $stages = $this->payload()['stages'];
        $stages[0]['resources'][0] = ['title' => 'مصدر', 'url' => 'javascript:alert(1)', 'type' => 'podcast', 'lang' => 'fr'];
        $stages[0]['items'] = [['type' => 'course', 'id' => 999], ['type' => 'lesson', 'id' => 1]];
        $stages[1] = ['title' => ''];

        $this->actingAs($this->editor())->postJson(route('api.learning-paths.store'), $this->payload(['slug' => 'Front End', 'icon' => 'rocket', 'stages' => $stages]))
            ->assertJsonValidationErrors([
                'slug', 'icon', 'stages.1.title',
                'stages.0.resources.0.url', 'stages.0.resources.0.type', 'stages.0.resources.0.lang', 'stages.0.items.1.type',
            ]);

        // a link to something deleted is caught once the rest is valid
        $stages = $this->payload()['stages'];
        $stages[0]['items'] = [['type' => 'course', 'id' => 999]];
        $this->actingAs($this->editor())->postJson(route('api.learning-paths.store'), $this->payload(['stages' => $stages]))
            ->assertJsonValidationErrors('stages.0.items.0.id');

        $this->actingAs($this->editor())->postJson(route('api.learning-paths.store'), $this->payload(['stages' => array_fill(0, 16, ['title' => 'مرحلة'])]))
            ->assertJsonValidationErrors('stages');
        $this->assertSame(0, LearningPath::query()->count());
    }

    public function test_the_editor_saves_the_whole_path_and_the_list_switch_only_shows_or_hides_it(): void
    {
        $path = LearningPath::factory()->create(['slug' => 'backend']);
        $editor = $this->editor();

        $this->actingAs($editor)->getJson(route('api.learning-paths.show', $path))
            ->assertOk()->assertJsonPath('data.stages.0.title', 'الأساسيات');

        $this->actingAs($editor)->putJson(route('api.learning-paths.update', $path), $this->payload(['slug' => 'backend', 'stages' => []]))
            ->assertOk()->assertJsonPath('data.title', 'مطوّر واجهات Frontend')->assertJsonPath('data.stages', []);

        $this->actingAs($editor)->patchJson(route('api.learning-paths.update', $path), ['is_published' => false])
            ->assertOk()->assertJsonPath('data.is_published', false)->assertJsonPath('data.title', 'مطوّر واجهات Frontend');

        $this->actingAs($editor)->putJson(route('api.learning-paths.update', $path), ['is_published' => true])
            ->assertJsonValidationErrors(['title', 'icon']);
    }

    public function test_paths_are_listed_in_order_moved_and_deleted(): void
    {
        $first = LearningPath::factory()->create(['position' => 1]);
        $second = LearningPath::factory()->hidden()->create(['position' => 2]);
        $editor = $this->editor();

        $this->actingAs($editor)->getJson(route('api.learning-paths.index'))
            ->assertOk()->assertJsonPath('data.*.id', [$first->id, $second->id])->assertJsonMissingPath('data.0.stages');

        $this->actingAs($editor)->postJson(route('api.learning-paths.move', $second), ['direction' => 'up'])
            ->assertOk()->assertJsonPath('data.*.id', [$second->id, $first->id]);
        $this->actingAs($editor)->postJson(route('api.learning-paths.move', $second), ['direction' => 'up'])
            ->assertJsonValidationErrors('direction');

        $this->actingAs($editor)->deleteJson(route('api.learning-paths.destroy', $first))->assertNoContent();
        $this->assertModelMissing($first);
    }

    public function test_support_reads_paths_but_cannot_change_them(): void
    {
        $path = LearningPath::factory()->create();
        $support = User::factory()->role(Role::Support)->create();

        $this->actingAs($support)->getJson(route('api.learning-paths.index'))->assertOk();
        $this->actingAs($support)->postJson(route('api.learning-paths.store'), $this->payload())->assertForbidden();
        $this->actingAs($support)->patchJson(route('api.learning-paths.update', $path), ['is_published' => false])->assertForbidden();
        $this->actingAs($support)->deleteJson(route('api.learning-paths.destroy', $path))->assertForbidden();
        $this->actingAs($support)->get(route('paths.edit', $path))->assertForbidden();
        $this->actingAs($this->editor())->get(route('paths.edit', $path))->assertOk()->assertSee('data-id="'.$path->id.'"', escape: false);
    }
}
