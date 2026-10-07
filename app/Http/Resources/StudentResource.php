<?php

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Student
 */
class StudentResource extends JsonResource
{
    private const STATE_LABELS = ['active' => 'نشط', 'inactive' => 'غير نشط', 'suspended' => 'موقوف'];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = $this->state();

        return [
            'id' => $this->id,
            'code' => 'S-'.(999 + $this->id),
            'name' => $this->name,
            'initial' => $this->initial,
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp_url' => $this->whatsappUrl(),
            // signed up on the site themselves (rather than added by the team)
            'has_account' => $this->password !== null,
            'country' => $this->country,
            'state' => $state,
            'state_label' => self::STATE_LABELS[$state],
            'courses' => (int) ($this->enrollments_count ?? $this->enrollments()->count()),
            'workshops' => (int) ($this->workshop_registrations_count ?? $this->workshopRegistrations()->count()),
            'joined' => $this->created_at->toDateString(),
            'last_active_at' => $this->last_active_at?->toIso8601String(),
        ];
    }
}
