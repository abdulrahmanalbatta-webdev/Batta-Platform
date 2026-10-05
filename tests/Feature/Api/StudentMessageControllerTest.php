<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StudentMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StudentMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_emails_chosen_students_with_their_name_and_skips_suspended(): void
    {
        Notification::fake();
        $sara = Student::factory()->create(['name' => 'سارة']);
        $suspended = Student::factory()->suspended()->create();

        $response = $this->actingAs(User::factory()->role(Role::Admin)->create())->postJson(route('api.students.messages.store'), [
            'ids' => [$sara->id, $suspended->id],
            'subject' => 'أهلاً {الاسم}',
            'body' => "مرحباً {الاسم}،\n\nدفعة جديدة.",
        ]);

        $response->assertOk()->assertJsonPath('sent', 1);
        Notification::assertSentTo($sara, StudentMessage::class, function (StudentMessage $message) use ($sara) {
            $mail = $message->toMail($sara);

            return $mail->subject === 'أهلاً سارة' && $mail->introLines === ['مرحباً سارة،', 'دفعة جديدة.'];
        });
        Notification::assertNotSentTo($suspended, StudentMessage::class);
    }

    public function test_message_is_queued(): void
    {
        $this->assertContains(ShouldQueue::class, class_implements(StudentMessage::class));
    }

    public function test_missing_subject_and_body_return_422(): void
    {
        $student = Student::factory()->create();

        $response = $this->actingAs(User::factory()->role(Role::Support)->create())->postJson(route('api.students.messages.store'), ['ids' => [$student->id]]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['subject' => 'حقل الموضوع مطلوب.', 'body' => 'حقل الرسالة مطلوب.']);
    }

    public function test_editor_cannot_email_students_and_gets_403(): void
    {
        $student = Student::factory()->create();

        $this->actingAs(User::factory()->role(Role::Editor)->create())
            ->postJson(route('api.students.messages.store'), ['ids' => [$student->id], 'subject' => 'x', 'body' => 'y'])
            ->assertForbidden();
    }
}
