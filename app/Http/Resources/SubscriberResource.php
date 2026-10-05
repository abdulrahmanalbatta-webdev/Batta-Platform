<?php

namespace App\Http\Resources;

use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscriber
 */
class SubscriberResource extends JsonResource
{
    private const SOURCES = ['site' => 'نموذج الموقع', 'unsubscribe' => 'رابط الإلغاء'];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'source' => $this->source,
            'source_label' => self::SOURCES[$this->source] ?? $this->source,
            'is_active' => $this->isActive(),
            'status_label' => $this->isActive() ? 'مشترك' : 'ألغى الاشتراك',
            // also a student on the platform (uses withExists('student') when loaded)
            'is_student' => (bool) ($this->is_student ?? false),
            'subscribed_at' => $this->created_at->toDateString(),
            'unsubscribed_at' => $this->unsubscribed_at?->toDateString(),
        ];
    }
}
