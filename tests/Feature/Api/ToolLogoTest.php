<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ToolLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function editor(): User
    {
        return User::factory()->role(Role::Editor)->create();
    }

    private function png(string $name = 'logo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    }

    public function test_uploaded_logo_reaches_the_dashboard_and_the_site(): void
    {
        $tool = Tool::factory()->create(['is_published' => true]);

        $response = $this->actingAs($this->editor())->postJson(route('api.tools.logo.store', $tool), ['logo' => $this->png()]);

        $response->assertOk()->assertJsonPath('data.logo_url', Storage::disk('public')->url($tool->fresh()->logo_path));
        Storage::disk('public')->assertExists($tool->fresh()->logo_path);
        $this->getJson(route('site.tools.index'))->assertJsonPath('data.0.tools.0.logo_url', Storage::disk('public')->url($tool->fresh()->logo_path));
    }

    public function test_a_new_logo_replaces_the_old_file(): void
    {
        $tool = Tool::factory()->create();
        $editor = $this->editor();
        $this->actingAs($editor)->postJson(route('api.tools.logo.store', $tool), ['logo' => $this->png()]);
        $old = $tool->fresh()->logo_path;

        $this->actingAs($editor)->postJson(route('api.tools.logo.store', $tool), ['logo' => $this->png('new.png')])->assertOk();

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($tool->fresh()->logo_path);
    }

    public function test_removing_the_logo_goes_back_to_the_letters(): void
    {
        $tool = Tool::factory()->create();
        $editor = $this->editor();
        $this->actingAs($editor)->postJson(route('api.tools.logo.store', $tool), ['logo' => $this->png()]);
        $path = $tool->fresh()->logo_path;

        $this->actingAs($editor)->deleteJson(route('api.tools.logo.destroy', $tool))->assertOk()->assertJsonPath('data.logo_url', null);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($tool->fresh()->logo_path);
    }

    public function test_deleting_a_tool_deletes_its_logo(): void
    {
        $tool = Tool::factory()->create();
        $editor = $this->editor();
        $this->actingAs($editor)->postJson(route('api.tools.logo.store', $tool), ['logo' => $this->png()]);
        $path = $tool->fresh()->logo_path;

        $this->actingAs($editor)->deleteJson(route('api.tools.destroy', $tool))->assertNoContent();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_svg_and_non_images_are_refused(): void
    {
        $tool = Tool::factory()->create();

        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->actingAs($this->editor())->postJson(route('api.tools.logo.store', $tool), ['logo' => $svg])->assertUnprocessable()->assertJsonValidationErrors('logo');

        $this->assertNull($tool->fresh()->logo_path);
    }

    public function test_support_cannot_change_a_logo(): void
    {
        $tool = Tool::factory()->create();

        $this->actingAs(User::factory()->role(Role::Support)->create())
            ->postJson(route('api.tools.logo.store', $tool), ['logo' => $this->png()])
            ->assertForbidden();
    }
}
