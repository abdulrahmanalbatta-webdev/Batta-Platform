<?php

namespace App\Http\Resources\Site;

use App\Enums\MentorshipStatus;
use App\Models\MentorshipRequest;
use App\Support\MentorshipSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A student's own mentorship request: where it stands, and while it waits for the transfer, where to pay.
 * The owner's notes are never in it.
 *
 * @mixin MentorshipRequest
 */
class MentorshipRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $awaitsPayment = $this->status === MentorshipStatus::Accepted;

        return [
            'id' => $this->id,
            'number' => $this->number(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'package_name' => $this->package_name,
            'price' => $this->price,
            'goal' => $this->goal,
            'decision_note' => in_array($this->status, [MentorshipStatus::Rejected, MentorshipStatus::Accepted], true) ? $this->decision_note : null,
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'payment_due_at' => $this->payment_due_at?->toIso8601String(),
            'wallets' => $awaitsPayment ? app(MentorshipSettings::class)->wallets() : [],
            'proof_uploaded_at' => $this->proof_uploaded_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'session_at' => $this->session_at?->toIso8601String(),
            'meeting_url' => $this->status === MentorshipStatus::Scheduled ? $this->meeting_url : null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
