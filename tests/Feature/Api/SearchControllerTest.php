<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_matches_with_the_page_that_shows_them(): void
    {
        $student = Student::factory()->create(['name' => 'رامي حمدان', 'email' => 'rami@mail.com']);
        $lead = Lead::factory()->create(['name' => 'رامي حمدان', 'company' => 'محمصة البن']);
        $conversation = Conversation::factory()->create(['name' => 'رامي حمدان']);
        Course::factory()->create(['title' => 'أساسيات الويب']);

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.search', ['q' => 'رامي']));

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.group', 'students')
            ->assertJsonPath('data.0.items.0.params', ['q' => 'rami@mail.com'])
            ->assertJsonPath('data.1.group', 'leads')
            ->assertJsonPath('data.1.items.0', ['title' => 'محمصة البن', 'subtitle' => 'رامي حمدان · جديد', 'page' => 'leads', 'params' => ['lead' => $lead->id]])
            ->assertJsonPath('data.2.items.0.params', ['c' => $conversation->id]);
    }

    public function test_finds_an_order_by_number_or_by_student(): void
    {
        $order = Order::factory()->create(['item_name' => 'Git']);
        Order::factory()->count(2)->create();

        $member = User::factory()->create();

        $this->actingAs($member)->getJson(route('api.search', ['q' => $order->number()]))
            ->assertJsonPath('data.0.group', 'orders')
            ->assertJsonCount(1, 'data.0.items')
            ->assertJsonPath('data.0.items.0.params', ['q' => $order->number()]);
        $this->actingAs($member)->getJson(route('api.search', ['q' => $order->student->email]))
            ->assertJsonPath('data.1.items.0.title', $order->number().' · Git');
    }

    public function test_courses_open_their_edit_page_and_coupons_match_by_code(): void
    {
        $course = Course::factory()->create(['title' => 'Next.js من الصفر']);
        Coupon::factory()->create(['code' => 'NEXT20', 'value' => 20]);

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.search', ['q' => 'next']));

        $response->assertJsonPath('data.0.items.0.page', 'course-edit')
            ->assertJsonPath('data.0.items.0.params', ['id' => $course->id])
            ->assertJsonPath('data.1.group', 'coupons')
            ->assertJsonPath('data.1.items.0.subtitle', '20%');
    }

    public function test_at_most_five_per_group(): void
    {
        Student::factory()->count(7)->sequence(fn ($sequence) => ['name' => 'سارة '.$sequence->index])->create();

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.search', ['q' => 'سارة']));

        $response->assertJsonCount(5, 'data.0.items');
    }

    public function test_wildcards_are_searched_literally(): void
    {
        Student::factory()->create(['name' => 'Rami']);

        $response = $this->actingAs(User::factory()->create())->getJson(route('api.search', ['q' => '%%']));

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_needs_two_characters_and_a_member(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('api.search', ['q' => 'a']))->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $this->getJson(route('api.search', ['q' => 'abc']))->assertUnauthorized();
    }
}
