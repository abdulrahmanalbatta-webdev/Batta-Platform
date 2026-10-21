<?php

namespace App\Http\Controllers\Api;

use App\Enums\MentorshipStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MentorshipRequestResource;
use App\Models\MentorshipRequest;
use App\Notifications\MentorshipUpdate;
use App\Support\MentorshipSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MentorshipRequestStatusController extends Controller
{
    /**
     * The statuses each step can be taken from.
     */
    private const FROM = [
        'accept' => [MentorshipStatus::Pending],
        'reject' => [MentorshipStatus::Pending, MentorshipStatus::Accepted, MentorshipStatus::ProofSubmitted],
        'confirm_payment' => [MentorshipStatus::Accepted, MentorshipStatus::ProofSubmitted],
        'reject_proof' => [MentorshipStatus::ProofSubmitted],
        'schedule' => [MentorshipStatus::Paid, MentorshipStatus::Scheduled],
        'complete' => [MentorshipStatus::Paid, MentorshipStatus::Scheduled],
    ];

    /**
     * Move a request one step: accept or reject it, confirm the payment or turn down the receipt, book the
     * session, or mark it complete. The student gets an email at each step but the last.
     *
     * @throws ValidationException
     */
    public function update(Request $request, MentorshipRequest $mentorshipRequest, MentorshipSettings $settings): MentorshipRequestResource
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(array_keys(self::FROM))],
            'note' => ['nullable', 'required_if:action,reject_proof', 'string', 'max:1000'],
            'session_at' => ['required_if:action,schedule', 'nullable', 'date', 'after:now'],
            'meeting_url' => ['nullable', 'url:https', 'max:500'],
        ], [], ['note' => 'السبب', 'session_at' => 'موعد الجلسة', 'meeting_url' => 'رابط اللقاء']);

        $action = $validated['action'];

        DB::transaction(function () use ($mentorshipRequest, $settings, $validated, $action): void {
            // locked with the other seat holders, so two acceptances can't both take the last seat
            MentorshipRequest::query()->whereIn('status', MentorshipStatus::holdingSeat())->lockForUpdate()->get();
            $mentorshipRequest->refresh();

            if (! in_array($mentorshipRequest->status, self::FROM[$action], true)) {
                throw ValidationException::withMessages(['action' => 'لا يمكن تنفيذ هذه الخطوة على طلب حالته "'.$mentorshipRequest->status->label().'".']);
            }

            if ($action === 'accept' && $settings->seatsLeft() === 0) {
                throw ValidationException::withMessages(['action' => 'المقاعد مكتملة. زِد عدد المقاعد من الإعدادات أو انتظر حتى يكتمل طلب آخر.']);
            }

            $hours = $settings->all()['payment_hours'];

            $mentorshipRequest->forceFill(match ($action) {
                'accept' => ['status' => MentorshipStatus::Accepted, 'accepted_at' => now(), 'payment_due_at' => now()->addHours($hours), 'decision_note' => null],
                'reject' => ['status' => MentorshipStatus::Rejected, 'decision_note' => $validated['note'] ?? null],
                'confirm_payment' => ['status' => MentorshipStatus::Paid, 'paid_at' => now(), 'decision_note' => null],
                'reject_proof' => ['status' => MentorshipStatus::Accepted, 'payment_due_at' => now()->addHours($hours), 'decision_note' => $validated['note']],
                'schedule' => ['status' => MentorshipStatus::Scheduled, 'session_at' => $validated['session_at'], 'meeting_url' => $validated['meeting_url'] ?? null],
                'complete' => ['status' => MentorshipStatus::Completed, 'completed_at' => now()],
            })->save();
        });

        $event = ['accept' => 'accepted', 'reject' => 'rejected', 'confirm_payment' => 'paid', 'reject_proof' => 'proof_rejected', 'schedule' => 'scheduled'][$action] ?? null;

        if ($event !== null) {
            $mentorshipRequest->student->notify(new MentorshipUpdate($mentorshipRequest, $event));
        }

        return new MentorshipRequestResource($mentorshipRequest->load('student'));
    }
}
