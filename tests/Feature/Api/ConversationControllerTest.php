<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Lead;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConversationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_conversations_by_latest_activity_with_last_message(): void
    {
        $lead = Lead::factory()->create(['company' => 'محمصة البن']);
        $older = Conversation::factory()->create(['last_message_at' => now()->subDay()]);
        $recent = Conversation::factory()->unread()->create(['lead_id' => $lead->id, 'last_message_at' => now()]);
        ConversationMessage::factory()->for($recent)->create(['body' => 'أول رسالة', 'created_at' => now()->subMinute()]);
        ConversationMessage::factory()->for($recent)->create(['body' => 'آخر رسالة']);

        $response = $this->actingAs(User::factory()->role(Role::Accountant)->create())->getJson(route('api.conversations.index'));

        $response->assertOk()
            ->assertJsonPath('data.0.id', $recent->id)
            ->assertJsonPath('data.0.unread', true)
            ->assertJsonPath('data.0.label', 'عميل · محمصة البن')
            ->assertJsonPath('data.0.last_message.body', 'آخر رسالة')
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.1.label', 'زائر')
            ->assertJsonMissingPath('data.0.messages');
    }

    public function test_shows_messages_oldest_first_with_sender(): void
    {
        $member = User::factory()->create(['name' => 'يوسف']);
        $conversation = Conversation::factory()->create(['student_id' => Student::factory()->create()->id]);
        ConversationMessage::factory()->for($conversation)->create(['body' => 'سؤال', 'created_at' => now()->subHour()]);
        ConversationMessage::factory()->for($conversation)->create(['from_contact' => false, 'user_id' => $member->id, 'body' => 'جواب']);

        $response = $this->actingAs($member)->getJson(route('api.conversations.show', $conversation));

        $response->assertOk()
            ->assertJsonPath('data.label', 'طالب')
            ->assertJsonPath('data.messages.0.body', 'سؤال')
            ->assertJsonPath('data.messages.0.sender', null)
            ->assertJsonPath('data.messages.1.sender', 'يوسف');
    }

    public function test_starting_a_conversation_with_a_client_reuses_the_existing_one(): void
    {
        $lead = Lead::factory()->create(['name' => 'رامي', 'email' => 'rami@example.com']);
        $support = User::factory()->role(Role::Support)->create();

        $first = $this->actingAs($support)->postJson(route('api.conversations.store'), ['lead_id' => $lead->id]);
        $second = $this->actingAs($support)->postJson(route('api.conversations.store'), ['lead_id' => $lead->id]);

        $first->assertCreated()->assertJsonPath('data.email', 'rami@example.com')->assertJsonPath('data.unread', false);
        $second->assertOk()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertSame(1, Conversation::count());
    }

    public function test_starts_a_conversation_with_a_student(): void
    {
        $student = Student::factory()->create();

        $response = $this->actingAs(User::factory()->owner()->create())->postJson(route('api.conversations.store'), ['student_id' => $student->id]);

        $response->assertCreated()->assertJsonPath('data.student_id', $student->id);
    }

    public function test_a_client_without_email_cannot_be_messaged(): void
    {
        $lead = Lead::factory()->create(['email' => null]);

        $response = $this->actingAs(User::factory()->owner()->create())->postJson(route('api.conversations.store'), ['lead_id' => $lead->id]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['lead_id' => 'لا يوجد بريد إلكتروني لهذا العميل، أضفه أولاً.']);
    }

    public function test_marks_read_and_unread(): void
    {
        $conversation = Conversation::factory()->unread()->create();
        $support = User::factory()->role(Role::Support)->create();

        $this->actingAs($support)->postJson(route('api.conversations.read.store', $conversation))->assertOk()->assertJsonPath('data.unread', false);
        $this->actingAs($support)->deleteJson(route('api.conversations.read.destroy', $conversation))->assertOk()->assertJsonPath('data.unread', true);
    }

    public function test_deleting_a_conversation_removes_messages_and_attachment_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('conversations/1/file.pdf', 'pdf');
        $conversation = Conversation::factory()->create();
        $message = ConversationMessage::factory()->for($conversation)->create(['attachment_path' => 'conversations/1/file.pdf', 'attachment_name' => 'file.pdf']);

        $this->actingAs(User::factory()->owner()->create())->deleteJson(route('api.conversations.destroy', $conversation))->assertNoContent();

        $this->assertModelMissing($message);
        Storage::disk('local')->assertMissing('conversations/1/file.pdf');
    }

    public function test_editor_and_accountant_only_read(): void
    {
        $conversation = Conversation::factory()->create();
        $lead = Lead::factory()->create();

        foreach ([Role::Editor, Role::Accountant] as $role) {
            $member = User::factory()->role($role)->create();

            $this->actingAs($member)->getJson(route('api.conversations.show', $conversation))->assertOk();
            $this->actingAs($member)->postJson(route('api.conversations.store'), ['lead_id' => $lead->id])->assertForbidden();
            $this->actingAs($member)->postJson(route('api.conversations.read.store', $conversation))->assertForbidden();
            $this->actingAs($member)->postJson(route('api.conversations.messages.store', $conversation), ['body' => 'x'])->assertForbidden();
            $this->actingAs($member)->deleteJson(route('api.conversations.destroy', $conversation))->assertForbidden();
        }
    }
}
