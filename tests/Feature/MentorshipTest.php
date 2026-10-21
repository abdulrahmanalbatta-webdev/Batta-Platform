<?php

namespace Tests\Feature;

use App\Enums\MentorshipStatus;
use App\Models\MentorshipRequest;
use App\Models\Student;
use App\Models\User;
use App\Notifications\Alerts\MentorshipRequested;
use App\Notifications\MentorshipUpdate;
use App\Support\MentorshipSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MentorshipTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function signIn(Student $student): array
    {
        return ['Authorization' => 'Bearer '.$student->createToken('web')->plainTextToken];
    }

    /**
     * Requests open, with a wallet to pay into.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function openMentorship(array $overrides = []): void
    {
        $settings = MentorshipSettings::defaults();
        $settings['enabled'] = true;
        $settings['wallets']['palpay'] = ['number' => '0599000000', 'holder' => 'عبد الرحمن البطه'];

        app(MentorshipSettings::class)->update(array_replace($settings, $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function application(array $overrides = []): array
    {
        return [
            'package_id' => 'monthly',
            'goal' => 'أريد بناء أول مشروع حقيقي بـ Laravel',
            'background' => 'أنهيت أساسيات PHP وقاعدة البيانات',
            'availability' => 'مساءً بعد الساعة 6',
            'accept_policy' => true,
            ...$overrides,
        ];
    }

    public function test_the_page_shows_packages_and_seats_but_never_the_wallet_numbers(): void
    {
        $this->openMentorship();
        MentorshipRequest::factory()->accepted()->create();

        $this->getJson(route('site.mentorship'))
            ->assertOk()
            ->assertJsonPath('data.accepting', true)
            ->assertJsonPath('data.seats_left', 9)
            ->assertJsonPath('data.payment_methods', ['بال باي'])
            ->assertJsonPath('data.packages.1.price', 150)
            ->assertDontSee('0599000000');
    }

    public function test_requests_stay_closed_until_switched_on_with_a_wallet(): void
    {
        $this->getJson(route('site.mentorship'))->assertJsonPath('data.accepting', false);

        $this->postJson(route('site.me.mentorship.store'), $this->application(), $this->signIn(Student::factory()->create()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mentorship' => 'مغلق']);
    }

    public function test_a_student_applies_and_only_the_owner_is_alerted(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $admin = User::factory()->admin()->create();
        $this->openMentorship();
        $student = Student::factory()->create();

        $response = $this->postJson(route('site.me.mentorship.store'), $this->application(), $this->signIn($student));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.package_name', 'إرشاد شهري')
            ->assertJsonPath('data.price', 150)
            ->assertJsonPath('data.wallets', []);
        Notification::assertSentTo($student, MentorshipUpdate::class, fn (MentorshipUpdate $mail): bool => $mail->event === 'received');
        Notification::assertSentTo($owner, MentorshipRequested::class);
        Notification::assertNotSentTo($admin, MentorshipRequested::class);
    }

    public function test_a_student_has_one_open_request_at_a_time(): void
    {
        $this->openMentorship();
        $student = Student::factory()->create();
        MentorshipRequest::factory()->for($student)->create();

        $this->postJson(route('site.me.mentorship.store'), $this->application(), $this->signIn($student))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mentorship' => 'قيد المتابعة']);
    }

    public function test_no_one_applies_once_the_seats_are_taken(): void
    {
        $this->openMentorship(['capacity' => 1]);
        MentorshipRequest::factory()->paid()->create();

        $this->postJson(route('site.me.mentorship.store'), $this->application(), $this->signIn(Student::factory()->create()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mentorship' => 'المقاعد مكتملة']);
    }

    public function test_the_application_needs_the_policy_and_a_real_package(): void
    {
        $this->openMentorship();

        $this->postJson(route('site.me.mentorship.store'), $this->application(['package_id' => 'gold', 'accept_policy' => false]), $this->signIn(Student::factory()->create()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['package_id', 'accept_policy']);
    }

    public function test_an_accepted_student_sees_the_wallets_and_sends_the_receipt(): void
    {
        Notification::fake();
        Storage::fake(MentorshipRequest::proofDisk());
        $owner = User::factory()->owner()->create();
        $this->openMentorship();
        $request = MentorshipRequest::factory()->accepted()->create();
        $headers = $this->signIn($request->student);

        $this->getJson(route('site.me.mentorship.show'), $headers)
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.wallets.0.number', '0599000000');

        $this->post(route('site.me.mentorship.proof'), ['proof' => UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf'), 'transaction_ref' => 'TX-778'], [...$headers, 'Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', 'proof_submitted')
            ->assertJsonPath('data.wallets', []);

        $request->refresh();
        $this->assertSame('TX-778', $request->transaction_ref);
        Storage::disk(MentorshipRequest::proofDisk())->assertExists($request->proof_path);
        Notification::assertSentTo($owner, MentorshipRequested::class, fn (MentorshipRequested $alert): bool => $alert->receipt);
    }

    public function test_no_receipt_after_the_payment_window_closed(): void
    {
        $request = MentorshipRequest::factory()->accepted()->create(['payment_due_at' => now()->subMinute()]);

        $this->post(route('site.me.mentorship.proof'), ['proof' => UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf')], [...$this->signIn($request->student), 'Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('proof');
    }

    public function test_only_the_owner_reaches_the_requests_and_the_receipts(): void
    {
        $request = MentorshipRequest::factory()->withProof()->create();

        $this->actingAs(User::factory()->admin()->create())->getJson(route('api.mentorship-requests.index'))->assertForbidden();
        $this->getJson(route('api.mentorship-requests.proof', $request))->assertForbidden();
        $this->get(route('mentorship.index'))->assertForbidden();
        $this->getJson(route('api.mentorship-settings.show'))->assertForbidden();
    }

    public function test_the_owner_accepts_and_the_student_gets_the_payment_window(): void
    {
        Notification::fake();
        $this->openMentorship(['payment_hours' => 12]);
        $request = MentorshipRequest::factory()->create();

        $this->actingAs(User::factory()->owner()->create())
            ->putJson(route('api.mentorship-requests.status.update', $request), ['action' => 'accept'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.student.email', $request->student->email);

        $this->assertEqualsWithDelta(now()->addHours(12)->timestamp, $request->fresh()->payment_due_at->timestamp, 5);
        Notification::assertSentTo($request->student, MentorshipUpdate::class, fn (MentorshipUpdate $mail): bool => $mail->event === 'accepted');
    }

    public function test_the_owner_cannot_accept_past_the_capacity(): void
    {
        $this->openMentorship(['capacity' => 1]);
        MentorshipRequest::factory()->accepted()->create();
        $request = MentorshipRequest::factory()->create();

        $this->actingAs(User::factory()->owner()->create())
            ->putJson(route('api.mentorship-requests.status.update', $request), ['action' => 'accept'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action' => 'المقاعد مكتملة']);
    }

    public function test_a_turned_down_receipt_needs_a_reason_and_opens_a_new_window(): void
    {
        Notification::fake();
        $request = MentorshipRequest::factory()->withProof()->create(['payment_due_at' => now()->subHour()]);
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->putJson(route('api.mentorship-requests.status.update', $request), ['action' => 'reject_proof'])
            ->assertJsonValidationErrors('note');

        $this->putJson(route('api.mentorship-requests.status.update', $request), ['action' => 'reject_proof', 'note' => 'المبلغ ناقص'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.decision_note', 'المبلغ ناقص');

        $this->assertTrue($request->fresh()->awaitsPayment());
        Notification::assertSentTo($request->student, MentorshipUpdate::class, fn (MentorshipUpdate $mail): bool => $mail->event === 'proof_rejected');
    }

    public function test_the_owner_confirms_the_payment_then_books_the_session(): void
    {
        Notification::fake();
        $request = MentorshipRequest::factory()->withProof()->create();
        $this->actingAs(User::factory()->owner()->create());

        $this->putJson(route('api.mentorship-requests.status.update', $request), ['action' => 'schedule', 'session_at' => now()->addDays(2)->toIso8601String()])
            ->assertUnprocessable();

        $this->putJson(route('api.mentorship-requests.status.update', $request), ['action' => 'confirm_payment'])
            ->assertJsonPath('data.status', 'paid');

        $this->putJson(route('api.mentorship-requests.status.update', $request), ['action' => 'schedule', 'session_at' => now()->subDay()->toIso8601String()])
            ->assertJsonValidationErrors('session_at');

        $this->putJson(route('api.mentorship-requests.status.update', $request), ['action' => 'schedule', 'session_at' => now()->addDays(2)->toIso8601String(), 'meeting_url' => 'https://meet.google.com/abc-defg-hij'])
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled');

        $this->getJson(route('site.me.mentorship.show'), $this->signIn($request->student))
            ->assertJsonPath('data.meeting_url', 'https://meet.google.com/abc-defg-hij');
        Notification::assertSentTo($request->student, MentorshipUpdate::class, fn (MentorshipUpdate $mail): bool => $mail->event === 'scheduled');
    }

    public function test_the_owner_opens_the_receipt(): void
    {
        Storage::fake(MentorshipRequest::proofDisk());
        $request = MentorshipRequest::factory()->withProof()->create();
        Storage::disk(MentorshipRequest::proofDisk())->put($request->proof_path, 'receipt');

        $this->actingAs(User::factory()->owner()->create())
            ->get(route('api.mentorship-requests.proof', $request))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_the_owner_saves_the_settings(): void
    {
        $settings = MentorshipSettings::defaults();
        $settings['packages'][] = ['name' => 'مراجعة سيرة ذاتية', 'price' => 30, 'description' => 'جلسة قصيرة لمراجعة السيرة الذاتية.', 'features' => []];
        $this->actingAs(User::factory()->owner()->create());

        $this->putJson(route('api.mentorship-settings.update'), [...$settings, 'payment_hours' => 48])
            ->assertJsonValidationErrors('payment_hours');

        $this->putJson(route('api.mentorship-settings.update'), [...$settings, 'capacity' => 4])
            ->assertOk()
            ->assertJsonPath('data.capacity', 4)
            ->assertJsonPath('data.packages.3.price', 30);

        $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', app(MentorshipSettings::class)->all()['packages'][3]['id']);
    }

    public function test_unpaid_requests_expire_and_free_the_seat(): void
    {
        Notification::fake();
        $late = MentorshipRequest::factory()->accepted()->create(['payment_due_at' => now()->subMinute()]);
        $onTime = MentorshipRequest::factory()->accepted()->create();

        $this->artisan('mentorship:expire-payments')->assertSuccessful();

        $this->assertSame(MentorshipStatus::Expired, $late->fresh()->status);
        $this->assertSame(MentorshipStatus::Accepted, $onTime->fresh()->status);
        Notification::assertSentTo($late->student, MentorshipUpdate::class, fn (MentorshipUpdate $mail): bool => $mail->event === 'expired');
        Notification::assertNotSentTo($onTime->student, MentorshipUpdate::class);
    }

    public function test_deleting_a_student_deletes_their_receipts(): void
    {
        Storage::fake(MentorshipRequest::proofDisk());
        $request = MentorshipRequest::factory()->withProof()->create();
        Storage::disk(MentorshipRequest::proofDisk())->put($request->proof_path, 'receipt');

        $request->student->delete();

        Storage::disk(MentorshipRequest::proofDisk())->assertMissing($request->proof_path);
    }

    public function test_every_email_renders(): void
    {
        $request = MentorshipRequest::factory()->create(['session_at' => now()->addDay(), 'payment_due_at' => now()->addDay(), 'decision_note' => 'سبب']);

        foreach (MentorshipUpdate::EVENTS as $event) {
            $mail = (new MentorshipUpdate($request, $event))->toMail($request->student);
            $this->assertNotEmpty($mail->subject, $event);
        }
    }
}
