<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Notifications\ConversationReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConversationMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_is_stored_marks_the_conversation_read_and_is_emailed_to_the_contact(): void
    {
        Notification::fake();
        $this->freezeSecond();
        $conversation = Conversation::factory()->unread()->create(['name' => 'رامي', 'email' => 'rami@example.com', 'last_message_at' => now()->subDay()]);
        $support = User::factory()->role(Role::Support)->create(['name' => 'يوسف']);

        $response = $this->actingAs($support)->postJson(route('api.conversations.messages.store', $conversation), ['body' => "  أهلاً رامي  \n\nالعرض جاهز.  "]);

        $response->assertCreated()
            ->assertJsonPath('data.from_contact', false)
            ->assertJsonPath('data.sender', 'يوسف')
            ->assertJsonPath('data.body', "أهلاً رامي  \n\nالعرض جاهز.")
            ->assertJsonPath('data.attachment', null);
        $conversation->refresh();
        $this->assertFalse($conversation->isUnread());
        $this->assertTrue($conversation->last_message_at->equalTo(now()));
        Notification::assertSentOnDemand(ConversationReply::class, fn (ConversationReply $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === ['rami@example.com' => 'رامي']);
    }

    public function test_reply_with_only_an_attachment_stores_it_privately_and_attaches_it_to_the_email(): void
    {
        Notification::fake();
        Storage::fake('local');
        $conversation = Conversation::factory()->create(['name' => 'هبة']);

        $response = $this->actingAs(User::factory()->owner()->create())->post(route('api.conversations.messages.store', $conversation), [
            'attachment' => UploadedFile::fake()->create('العرض.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('data.attachment.name', 'العرض.pdf');
        $message = ConversationMessage::sole();
        Storage::disk('local')->assertExists($message->attachment_path);

        $mail = (new ConversationReply($message))->toMail(new AnonymousNotifiable);
        $this->assertSame('مرحباً هبة،', $mail->greeting);
        $this->assertSame('العرض.pdf', $mail->rawAttachments[0]['name']);
    }

    public function test_reply_needs_text_or_a_file_of_an_allowed_type(): void
    {
        $conversation = Conversation::factory()->create();
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->postJson(route('api.conversations.messages.store', $conversation), ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body' => 'اكتب رسالة أو أرفق ملفاً.']);

        $this->actingAs($owner)->post(route('api.conversations.messages.store', $conversation), [
            'attachment' => UploadedFile::fake()->create('run.exe', 10),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('attachment');
    }

    public function test_every_member_downloads_an_attachment_under_its_name(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('conversations/1/abc.pdf', 'pdf-bytes');
        $conversation = Conversation::factory()->create();
        $message = ConversationMessage::factory()->for($conversation)->create(['attachment_path' => 'conversations/1/abc.pdf', 'attachment_name' => 'offer.pdf']);

        $response = $this->actingAs(User::factory()->role(Role::Accountant)->create())->get(route('api.conversations.messages.attachment', [$conversation, $message]));

        $response->assertOk()->assertDownload('offer.pdf');
    }

    public function test_attachment_url_must_match_its_conversation(): void
    {
        $message = ConversationMessage::factory()->create(['attachment_path' => 'x.pdf', 'attachment_name' => 'x.pdf']);
        $other = Conversation::factory()->create();

        $this->actingAs(User::factory()->create())->getJson(route('api.conversations.messages.attachment', [$other, $message]))->assertNotFound();
    }

    public function test_message_without_attachment_has_nothing_to_download(): void
    {
        $message = ConversationMessage::factory()->create();

        $this->actingAs(User::factory()->create())->getJson(route('api.conversations.messages.attachment', [$message->conversation_id, $message]))->assertNotFound();
    }
}
