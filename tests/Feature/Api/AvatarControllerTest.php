<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A real 1×1 PNG, so the tests don't need the GD extension that UploadedFile::fake()->image() uses.
     */
    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    }

    public function test_uploads_avatar_and_deletes_previous_one(): void
    {
        Storage::fake('public');
        $member = User::factory()->create();
        $this->actingAs($member)->post(route('api.profile.avatar.store'), ['avatar' => $this->png('old.png')]);
        $previous = $member->fresh()->avatar_path;

        $response = $this->actingAs($member)->postJson(route('api.profile.avatar.store'), ['avatar' => $this->png('new.png')]);

        $path = $member->fresh()->avatar_path;
        $response->assertOk()->assertJsonPath('data.avatar_url', Storage::disk('public')->url($path));
        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertMissing($previous);
    }

    public function test_non_image_returns_422(): void
    {
        Storage::fake('public');
        $member = User::factory()->create();

        $response = $this->actingAs($member)->postJson(route('api.profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml'),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['avatar' => 'حقل الصورة يجب أن يكون صورة.']);
        $this->assertNull($member->fresh()->avatar_path);
    }

    public function test_image_over_3mb_returns_422(): void
    {
        Storage::fake('public');

        $response = $this->actingAs(User::factory()->create())->postJson(route('api.profile.avatar.store'), [
            'avatar' => $this->png('big.png')->size(3073),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['avatar' => 'حجم الصورة يجب ألا يتجاوز 3072 كيلوبايت.']);
    }
}
