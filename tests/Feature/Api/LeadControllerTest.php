<?php

namespace Tests\Feature\Api;

use App\Enums\LeadStage;
use App\Enums\Role;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_member_lists_requests_newest_first_with_labels(): void
    {
        Lead::factory()->create(['created_at' => now()->subDay()]);
        $latest = Lead::factory()->stage(LeadStage::Proposal)->create(['service' => 'stores']);

        $response = $this->actingAs(User::factory()->role(Role::Accountant)->create())->getJson(route('api.leads.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.number', 'L-'.$latest->id)
            ->assertJsonPath('data.0.stage_label', 'عرض مُرسل')
            ->assertJsonPath('data.0.service_label', 'المتاجر الإلكترونية');
    }

    public function test_admin_adds_request_in_new_column(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->postJson(route('api.leads.store'), [
            'name' => 'رامي حمدان',
            'company' => 'محمصة البن',
            'email' => 'rami@example.com',
            'service' => 'stores',
            'budget' => 2500,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.stage', 'new')
            ->assertJsonPath('data.budget', 2500);
        $this->assertDatabaseHas('leads', ['name' => 'رامي حمدان', 'stage' => 'new']);
    }

    public function test_request_needs_name_and_known_service(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->postJson(route('api.leads.store'), ['service' => 'logos', 'budget' => -5, 'email' => 'nope']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name', 'service', 'budget', 'email']);
    }

    public function test_moving_a_card_changes_only_its_stage(): void
    {
        $lead = Lead::factory()->create(['note' => 'ملاحظة']);

        $response = $this->actingAs(User::factory()->admin()->create())->patchJson(route('api.leads.update', $lead), ['stage' => 'won']);

        $response->assertOk()->assertJsonPath('data.stage_label', 'مقبول');
        $this->assertSame('ملاحظة', $lead->fresh()->note);
    }

    public function test_deleting_a_request_keeps_its_conversation(): void
    {
        $lead = Lead::factory()->create();
        $conversation = Conversation::factory()->create(['lead_id' => $lead->id]);

        $this->actingAs(User::factory()->owner()->create())->deleteJson(route('api.leads.destroy', $lead))->assertNoContent();

        $this->assertModelMissing($lead);
        $this->assertNull($conversation->fresh()->lead_id);
    }

    public function test_editor_cannot_change_the_board(): void
    {
        $lead = Lead::factory()->create();
        $editor = User::factory()->role(Role::Editor)->create();

        $this->actingAs($editor)->postJson(route('api.leads.store'), ['name' => 'x', 'service' => 'stores'])->assertForbidden();
        $this->actingAs($editor)->patchJson(route('api.leads.update', $lead), ['stage' => 'won'])->assertForbidden();
        $this->actingAs($editor)->deleteJson(route('api.leads.destroy', $lead))->assertForbidden();
    }
}
