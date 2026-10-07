<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->role(Role::Editor)->create();
    }

    public function test_lists_categories_in_order_with_tool_counts(): void
    {
        $backend = ToolCategory::factory()->create(['position' => 2]);
        $editors = ToolCategory::factory()->create(['position' => 1]);
        Tool::factory()->count(2)->for($backend, 'category')->create();

        $response = $this->actingAs(User::factory()->role(Role::Support)->create())->getJson(route('api.tool-categories.index'));

        $response->assertOk()
            ->assertJsonPath('data.*.id', [$editors->id, $backend->id])
            ->assertJsonPath('data.1.tools_count', 2);
    }

    public function test_adds_category_with_trimmed_name(): void
    {
        $response = $this->actingAs($this->editor())->postJson(route('api.tool-categories.store'), ['name' => '  الذكاء الاصطناعي ', 'color' => '#7c3aed']);

        $response->assertCreated()->assertJsonPath('data.name', 'الذكاء الاصطناعي')->assertJsonPath('data.tools_count', 0);
    }

    public function test_duplicate_name_returns_422(): void
    {
        ToolCategory::factory()->create(['name' => 'النشر']);

        $response = $this->actingAs($this->editor())->postJson(route('api.tool-categories.store'), ['name' => 'النشر', 'color' => '#0066ff']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name' => 'اسم التصنيف مستخدم مسبقاً.']);
    }

    public function test_invalid_color_returns_422(): void
    {
        $response = $this->actingAs($this->editor())->postJson(route('api.tool-categories.store'), ['name' => 'تصميم', 'color' => 'red;}body{']);

        $response->assertUnprocessable()->assertJsonValidationErrors('color');
    }

    public function test_renames_category(): void
    {
        $category = ToolCategory::factory()->create(['name' => 'الواجهات']);

        $response = $this->actingAs($this->editor())->patchJson(route('api.tool-categories.update', $category), ['name' => 'الواجهات الأمامية']);

        $response->assertOk()->assertJsonPath('data.name', 'الواجهات الأمامية');
    }

    public function test_deletes_empty_category(): void
    {
        $category = ToolCategory::factory()->create();

        $this->actingAs($this->editor())->deleteJson(route('api.tool-categories.destroy', $category))->assertNoContent();

        $this->assertModelMissing($category);
    }

    public function test_deleting_category_with_tools_requires_a_target(): void
    {
        $category = ToolCategory::factory()->create();
        Tool::factory()->for($category, 'category')->create();

        $response = $this->actingAs($this->editor())->deleteJson(route('api.tool-categories.destroy', $category));

        $response->assertUnprocessable()->assertJsonValidationErrors('move_to');
        $this->assertModelExists($category);
    }

    public function test_deleting_category_moves_its_tools(): void
    {
        $old = ToolCategory::factory()->create();
        $target = ToolCategory::factory()->create();
        $tool = Tool::factory()->for($old, 'category')->create();

        $response = $this->actingAs($this->editor())->deleteJson(route('api.tool-categories.destroy', $old).'?move_to='.$target->id);

        $response->assertNoContent();
        $this->assertModelMissing($old);
        $this->assertSame($target->id, $tool->fresh()->tool_category_id);
    }

    public function test_cannot_move_tools_into_the_category_being_deleted(): void
    {
        $category = ToolCategory::factory()->create();
        Tool::factory()->for($category, 'category')->create();

        $response = $this->actingAs($this->editor())->deleteJson(route('api.tool-categories.destroy', $category).'?move_to='.$category->id);

        $response->assertUnprocessable()->assertJsonValidationErrors('move_to');
    }

    public function test_move_down_swaps_with_next_category(): void
    {
        $first = ToolCategory::factory()->create(['position' => 1]);
        $second = ToolCategory::factory()->create(['position' => 2]);

        $response = $this->actingAs($this->editor())->postJson(route('api.tool-categories.move', $first), ['direction' => 'down']);

        $response->assertOk()->assertJsonPath('data.*.id', [$second->id, $first->id]);
    }

    public function test_support_cannot_delete_category_and_gets_403(): void
    {
        $category = ToolCategory::factory()->create();

        $this->actingAs(User::factory()->role(Role::Support)->create())->deleteJson(route('api.tool-categories.destroy', $category))->assertForbidden();

        $this->assertModelExists($category);
    }
}
