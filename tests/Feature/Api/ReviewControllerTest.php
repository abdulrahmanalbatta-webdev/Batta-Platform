<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Course;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_reviews_with_student_course_and_reply(): void
    {
        $owner = User::factory()->create(['name' => 'عبد الرحمن']);
        Review::factory()->create(['created_at' => now()->subDay()]);
        $review = Review::factory()->published()->create(['reply' => 'شكراً', 'replied_by' => $owner->id]);

        $response = $this->actingAs(User::factory()->role(Role::Editor)->create())->getJson(route('api.reviews.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', $review->student->name)
            ->assertJsonPath('data.0.course', $review->course->title)
            ->assertJsonPath('data.0.status_label', 'منشور')
            ->assertJsonPath('data.0.replied_by', 'عبد الرحمن');
    }

    public function test_support_publishes_and_hides_a_review(): void
    {
        $review = Review::factory()->create();
        $support = User::factory()->role(Role::Support)->create();

        $this->actingAs($support)->putJson(route('api.reviews.status.update', $review), ['status' => 'published'])
            ->assertOk()->assertJsonPath('data.status', 'published');
        $this->actingAs($support)->putJson(route('api.reviews.status.update', $review), ['status' => 'hidden'])
            ->assertOk()->assertJsonPath('data.status_label', 'مخفي');
    }

    public function test_a_review_cannot_go_back_to_pending(): void
    {
        $review = Review::factory()->published()->create();

        $response = $this->actingAs(User::factory()->create())->putJson(route('api.reviews.status.update', $review), ['status' => 'pending']);

        $response->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_reply_records_who_replied_and_can_be_removed(): void
    {
        $review = Review::factory()->create();
        $editor = User::factory()->role(Role::Editor)->create(['name' => 'سارة']);

        $this->actingAs($editor)->putJson(route('api.reviews.reply.update', $review), ['reply' => 'شكراً لك'])
            ->assertOk()
            ->assertJsonPath('data.reply', 'شكراً لك')
            ->assertJsonPath('data.replied_by', 'سارة');

        $this->actingAs($editor)->deleteJson(route('api.reviews.reply.destroy', $review))
            ->assertOk()
            ->assertJsonPath('data.reply', null);
        $this->assertNull($review->fresh()->replied_at);
    }

    public function test_empty_reply_is_rejected(): void
    {
        $review = Review::factory()->create();

        $response = $this->actingAs(User::factory()->create())->putJson(route('api.reviews.reply.update', $review), ['reply' => '']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['reply' => 'حقل الرد مطلوب.']);
    }

    public function test_deletes_a_review(): void
    {
        $review = Review::factory()->create();

        $this->actingAs(User::factory()->create())->deleteJson(route('api.reviews.destroy', $review))->assertNoContent();

        $this->assertModelMissing($review);
    }

    public function test_course_rating_averages_published_reviews_only(): void
    {
        $course = Course::factory()->create();
        Review::factory()->published()->for($course)->create(['rating' => 5]);
        Review::factory()->published()->for($course)->create(['rating' => 4]);
        Review::factory()->hidden()->for($course)->create(['rating' => 1]);
        Review::factory()->for($course)->create(['rating' => 1]);

        $member = User::factory()->create();

        $this->actingAs($member)->getJson(route('api.courses.index'))->assertJsonPath('data.0.rating', 4.5);
        $this->actingAs($member)->getJson(route('api.courses.show', $course))->assertJsonPath('data.rating', 4.5);
    }
}
