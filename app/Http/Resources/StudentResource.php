<?php

namespace App\Http\Resources;

use App\Models\Student;
use App\Support\CourseProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Student
 */
class StudentResource extends JsonResource
{
    private const STATE_LABELS = ['active' => 'نشط', 'inactive' => 'غير نشط', 'suspended' => 'موقوف'];

    /**
     * @param  array<int, int>  $progress  course id => percent, from CourseProgress
     */
    public function __construct(Student $resource, private array $progress = [])
    {
        parent::__construct($resource);
    }

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
            'country' => $this->country,
            'is_pro' => $this->isPro(),
            'pro_until' => $this->pro_until?->toDateString(),
            'state' => $state,
            'state_label' => self::STATE_LABELS[$state],
            'courses' => count($this->progress),
            'progress' => CourseProgress::average($this->progress),
            'spent' => $this->totalSpent(),
            'joined' => $this->created_at->toDateString(),
            'last_active_at' => $this->last_active_at?->toIso8601String(),
        ];
    }
}
