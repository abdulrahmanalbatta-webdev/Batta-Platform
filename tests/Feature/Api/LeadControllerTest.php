<?php

namespace Tests\Feature\Api;

use App\Enums\LeadStage;
use App\Enums\Role;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\User;
use App\Support\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_member_lists_requests_newest_first_with_labels(): void
    {
        Lead::factory()->create(['created_at' => now()->subDay()]);
        $latest = Lead::factory()->stage(LeadStage::Proposal)->create(['service' => 'ecommerce']);

        $response = $this->actingAs(User::factory()->role(Role::Support)->create())->getJson(route('api.leads.index'));

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
            'service' => 'ecommerce',
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

        $this->actingAs($editor)->postJson(route('api.leads.store'), ['name' => 'x', 'service' => 'ecommerce'])->assertForbidden();
        $this->actingAs($editor)->patchJson(route('api.leads.update', $lead), ['stage' => 'won'])->assertForbidden();
        $this->actingAs($editor)->deleteJson(route('api.leads.destroy', $lead))->assertForbidden();
    }

    public function test_services_follow_the_site_and_old_leads_keep_theirs(): void
    {
        $content = app(SiteContent::class);
        $admin = User::factory()->admin()->create();
        $old = Lead::factory()->create(['service' => 'training']);
        $content->update('services', [...$content->all()['services'], ['id' => 'seo', 'icon' => 'search', 'title' => 'تحسين محركات البحث', 'text' => 'ظهور أعلى', 'from' => null, 'duration' => null, 'features' => []]]);

        $this->actingAs($admin)->postJson(route('api.leads.store'), ['name' => 'عميل', 'service' => 'seo'])
            ->assertCreated()
            ->assertJsonPath('data.service_label', 'تحسين محركات البحث');
        $this->getJson(route('site.settings'))->assertJsonFragment(['value' => 'seo', 'label' => 'تحسين محركات البحث']);

        // "training" removed from the site: new leads can't pick it, the old lead keeps it and can still be edited
        $content->update('services', array_values(array_filter($content->all()['services'], fn (array $s): bool => $s['id'] !== 'training')));
        $this->actingAs($admin)->postJson(route('api.leads.store'), ['name' => 'آخر', 'service' => 'training'])->assertJsonValidationErrors('service');
        $this->actingAs($admin)->patchJson(route('api.leads.update', $old), ['service' => 'training', 'stage' => 'contacted'])
            ->assertOk()
            ->assertJsonPath('data.service_label', 'training');
    }
}
