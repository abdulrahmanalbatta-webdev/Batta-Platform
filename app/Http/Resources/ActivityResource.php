<?php

namespace App\Http\Resources;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Activity
 */
class ActivityResource extends JsonResource
{
    private const VERBS = ['created' => 'أضاف', 'updated' => 'عدّل', 'status' => 'غيّر حالة', 'deleted' => 'حذف', 'exported' => 'صدّر نسخة البيانات', 'wiped' => 'مسح'];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $label = $this->subject_type === null ? '' : (new ($this->subjectClass()))->activityLabel();
        $state = $this->properties['state'] ?? null;

        return [
            'id' => $this->id,
            'action' => $this->action,
            'user' => $this->user?->name ?? 'عضو محذوف',
            'initial' => $this->user?->initial,
            // e.g. "غيّر حالة الطلب «#1024» إلى «مسترد»"
            'description' => implode(' ', array_filter([self::VERBS[$this->action], $label, '«'.$this->subject_name.'»', $state ? 'إلى «'.$state.'»' : null])),
            'at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * @return class-string
     */
    private function subjectClass(): string
    {
        return $this->getActualClassNameForMorph($this->subject_type);
    }
}
