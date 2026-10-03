<?php

namespace Tests\Feature\Api;

use App\Enums\OrderItemType;
use App\Enums\Role;
use App\Enums\WorkshopFormat;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Models\Workshop;
use App\Notifications\WorkshopReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WorkshopControllerTest extends TestCase
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
            'title' => 'ابنِ ملف أعمالك في ساعتين',
            'description' => 'ورشة عملية',
            'date' => now()->addWeek()->toDateString(),
            'time' => '19:00',
            'format' => WorkshopFormat::Online->value,
            'place' => 'Zoom',
            'price' => 19,
            'seats' => 40,
            ...$overrides,
        ];
    }

    public function test_lists_workshops_soonest_first_with_state(): void
    {
        $later = Workshop::factory()->create(['date' => now()->addDays(20)->toDateString()]);
        $past = Workshop::factory()->past()->create();

        $response = $this->actingAs(User::factory()->role(Role::Support)->create())->getJson(route('api.workshops.index'));

        $response->assertOk()
            ->assertJsonPath('data.0.id', $past->id)
            ->assertJsonPath('data.0.state', 'ended')
            ->assertJsonPath('data.0.state_label', 'منتهية')
            ->assertJsonPath('data.1.id', $later->id)
            ->assertJsonPath('data.1.state', 'open')
            ->assertJsonPath('data.1.code', 'W-'.$later->id);
    }

    public function test_editor_adds_workshop(): void
    {
        $response = $this->actingAs($this->editor())->postJson(route('api.workshops.store'), $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.time', '19:00')
            ->assertJsonPath('data.format_label', 'أونلاين')
            ->assertJsonPath('data.price', 19)
            ->assertJsonPath('data.taken', 0);
        $this->assertDatabaseHas('workshops', ['title' => 'ابنِ ملف أعمالك في ساعتين', 'seats' => 40]);
    }

    public function test_new_workshop_in_the_past_returns_422(): void
    {
        $response = $this->actingAs($this->editor())->postJson(route('api.workshops.store'), $this->payload(['date' => now()->subDay()->toDateString()]));

        $response->assertUnprocessable()->assertJsonValidationErrors(['date' => 'تاريخ الورشة الجديدة لا يمكن أن يكون في الماضي.']);
    }

    public function test_past_workshop_can_still_be_edited(): void
    {
        $workshop = Workshop::factory()->past()->create();

        $response = $this->actingAs($this->editor())->putJson(route('api.workshops.update', $workshop), $this->payload(['date' => $workshop->date->toDateString(), 'title' => 'عنوان مصحح']));

        $response->assertOk()->assertJsonPath('data.title', 'عنوان مصحح')->assertJsonPath('data.state', 'ended');
    }

    public function test_empty_place_defaults_by_format(): void
    {
        $online = $this->actingAs($this->editor())->postJson(route('api.workshops.store'), $this->payload(['place' => null]));
        $inPerson = $this->postJson(route('api.workshops.store'), $this->payload(['place' => '', 'format' => WorkshopFormat::InPerson->value]));

        $online->assertJsonPath('data.place', 'Zoom');
        $inPerson->assertJsonPath('data.place', '—');
    }

    public function test_invalid_seats_and_price_return_422(): void
    {
        $response = $this->actingAs($this->editor())->postJson(route('api.workshops.store'), $this->payload(['seats' => 0, 'price' => -5]));

        $response->assertUnprocessable()->assertJsonValidationErrors(['seats', 'price']);
    }

    public function test_empty_payload_returns_422_for_required_fields(): void
    {
        $response = $this->actingAs($this->editor())->postJson(route('api.workshops.store'), []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['title', 'date', 'time', 'format', 'price', 'seats']);
    }

    public function test_deletes_workshop(): void
    {
        $workshop = Workshop::factory()->create();

        $this->actingAs($this->editor())->deleteJson(route('api.workshops.destroy', $workshop))->assertNoContent();

        $this->assertModelMissing($workshop);
    }

    public function test_accountant_cannot_edit_and_gets_403(): void
    {
        $workshop = Workshop::factory()->create();

        $response = $this->actingAs(User::factory()->role(Role::Accountant)->create())->putJson(route('api.workshops.update', $workshop), $this->payload());

        $response->assertForbidden();
    }

    private function registerFor(Workshop $workshop, ?Student $student = null): Order
    {
        return Order::factory()->create([
            'item_type' => OrderItemType::Workshop,
            'item_id' => $workshop->id,
            'student_id' => $student ?? Student::factory(),
        ]);
    }

    public function test_paid_orders_take_seats_and_fill_the_workshop(): void
    {
        $workshop = Workshop::factory()->create(['seats' => 2]);
        $this->registerFor($workshop);
        $this->registerFor($workshop);

        $response = $this->actingAs($this->editor())->getJson(route('api.workshops.index'));

        $response->assertJsonPath('data.0.taken', 2)->assertJsonPath('data.0.state', 'full');
    }

    public function test_lists_registered_students(): void
    {
        $workshop = Workshop::factory()->create();
        $order = $this->registerFor($workshop, Student::factory()->create(['name' => 'نور']));

        $response = $this->actingAs(User::factory()->role(Role::Support)->create())->getJson(route('api.workshops.registrations.index', $workshop));

        $response->assertOk()->assertJsonPath('data.0.name', 'نور')->assertJsonPath('data.0.order_number', $order->number());
    }

    public function test_reminder_emails_every_registered_student(): void
    {
        Notification::fake();
        $workshop = Workshop::factory()->create();
        $order = $this->registerFor($workshop);

        $response = $this->actingAs($this->editor())->postJson(route('api.workshops.reminders.store', $workshop));

        $response->assertOk()->assertJsonPath('sent', 1);
        Notification::assertSentTo($order->student, WorkshopReminder::class);
    }

    public function test_seats_cannot_drop_below_bookings(): void
    {
        $workshop = Workshop::factory()->create(['seats' => 5]);
        $this->registerFor($workshop);
        $this->registerFor($workshop);

        $response = $this->actingAs($this->editor())->putJson(route('api.workshops.update', $workshop), $this->payload(['seats' => 1]));

        $response->assertUnprocessable()->assertJsonValidationErrors('seats');
    }

    public function test_workshop_with_registrations_cannot_be_deleted(): void
    {
        $workshop = Workshop::factory()->create();
        $this->registerFor($workshop);

        $this->actingAs($this->editor())->deleteJson(route('api.workshops.destroy', $workshop))->assertUnprocessable();

        $this->assertModelExists($workshop);
    }
}
