<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->role(Role::Editor)->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ToolCategory $category, array $overrides = []): array
    {
        return [
            'name' => 'VS Code',
            'tool_category_id' => $category->id,
            'short' => 'VS',
            'color' => '#0066ff',
            'why' => 'محرري الأساسي',
            'since' => 2019,
            'url' => 'https://code.visualstudio.com',
            'is_affiliate' => false,
            'is_published' => true,
            ...$overrides,
        ];
    }

    public function test_lists_tools_in_position_order(): void
    {
        $second = Tool::factory()->create(['position' => 2]);
        $first = Tool::factory()->create(['position' => 1]);

        $response = $this->actingAs(User::factory()->role(Role::Support)->create())->getJson(route('api.tools.index'));

        $response->assertOk()->assertJsonPath('data.*.id', [$first->id, $second->id]);
    }

    public function test_editor_adds_tool_at_end_of_list(): void
    {
        $category = ToolCategory::factory()->create();
        Tool::factory()->create(['position' => 7]);

        $response = $this->actingAs($this->editor())->postJson(route('api.tools.store'), $this->payload($category));

        $response->assertCreated()->assertJsonPath('data.name', 'VS Code')->assertJsonPath('data.category_id', $category->id);
        $this->assertSame(8, Tool::where('name', 'VS Code')->sole()->position);
    }

    public function test_support_cannot_add_tool_and_gets_403(): void
    {
        $category = ToolCategory::factory()->create();

        $response = $this->actingAs(User::factory()->role(Role::Support)->create())->postJson(route('api.tools.store'), $this->payload($category));

        $response->assertForbidden();
        $this->assertDatabaseCount('tools', 0);
    }

    public function test_duplicate_name_returns_422(): void
    {
        $category = ToolCategory::factory()->create();
        Tool::factory()->create(['name' => 'VS Code']);

        $response = $this->actingAs($this->editor())->postJson(route('api.tools.store'), $this->payload($category));

        $response->assertUnprocessable()->assertJsonValidationErrors(['name' => 'اسم الأداة مستخدم مسبقاً.']);
    }

    public function test_non_http_url_returns_422(): void
    {
        $category = ToolCategory::factory()->create();

        $response = $this->actingAs($this->editor())->postJson(route('api.tools.store'), $this->payload($category, ['url' => 'javascript:alert(1)']));

        $response->assertUnprocessable()->assertJsonValidationErrors('url');
    }

    public function test_empty_payload_returns_422_for_required_fields(): void
    {
        $response = $this->actingAs($this->editor())->postJson(route('api.tools.store'), []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name', 'tool_category_id', 'short', 'color', 'why', 'since']);
    }

    public function test_publish_switch_updates_only_that_field(): void
    {
        $tool = Tool::factory()->draft()->create(['name' => 'Supabase']);

        $response = $this->actingAs($this->editor())->patchJson(route('api.tools.update', $tool), ['is_published' => true]);

        $response->assertOk()->assertJsonPath('data.is_published', true)->assertJsonPath('data.name', 'Supabase');
    }

    public function test_renaming_to_own_name_is_allowed(): void
    {
        $tool = Tool::factory()->create(['name' => 'Vercel']);

        $this->actingAs($this->editor())->patchJson(route('api.tools.update', $tool), ['name' => 'Vercel'])->assertOk();
    }

    public function test_deletes_tool(): void
    {
        $tool = Tool::factory()->create();

        $this->actingAs($this->editor())->deleteJson(route('api.tools.destroy', $tool))->assertNoContent();

        $this->assertModelMissing($tool);
    }

    public function test_move_up_swaps_with_previous_tool(): void
    {
        $first = Tool::factory()->create(['position' => 1]);
        $second = Tool::factory()->create(['position' => 2]);

        $response = $this->actingAs($this->editor())->postJson(route('api.tools.move', $second), ['direction' => 'up']);

        $response->assertOk()->assertJsonPath('data.*.id', [$second->id, $first->id]);
    }

    public function test_moving_first_tool_up_returns_422(): void
    {
        $first = Tool::factory()->create(['position' => 1]);
        Tool::factory()->create(['position' => 2]);

        $response = $this->actingAs($this->editor())->postJson(route('api.tools.move', $first), ['direction' => 'up']);

        $response->assertUnprocessable()->assertJsonValidationErrors('direction');
    }

    public function test_move_works_when_positions_are_equal(): void
    {
        $first = Tool::factory()->create(['position' => 0]);
        $second = Tool::factory()->create(['position' => 0]);

        $response = $this->actingAs($this->editor())->postJson(route('api.tools.move', $second), ['direction' => 'up']);

        $response->assertOk()->assertJsonPath('data.*.id', [$second->id, $first->id]);
    }
}
