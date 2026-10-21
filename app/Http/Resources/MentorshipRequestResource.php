<?php

namespace App\Http\Resources;

use App\Models\MentorshipRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A mentorship request as the owner reviews it on the dashboard.
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
        return [
            'id' => $this->id,
            'number' => $this->number(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'student' => [
                'id' => $this->student->id,
                'name' => $this->student->name,
                'email' => $this->student->email,
                'phone' => $this->student->phone,
                'whatsapp_url' => $this->student->whatsappUrl(),
            ],
            'package_id' => $this->package_id,
            'package_name' => $this->package_name,
            'price' => $this->price,
            'goal' => $this->goal,
            'background' => $this->background,
            'links' => $this->links,
            'availability' => $this->availability,
            'decision_note' => $this->decision_note,
            'notes' => $this->notes,
            'transaction_ref' => $this->transaction_ref,
            'proof_name' => $this->proof_name,
            'proof_url' => $this->proof_path === null ? null : route('api.mentorship-requests.proof', $this->resource),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'payment_due_at' => $this->payment_due_at?->toIso8601String(),
            'proof_uploaded_at' => $this->proof_uploaded_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'session_at' => $this->session_at?->toIso8601String(),
            'meeting_url' => $this->meeting_url,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
