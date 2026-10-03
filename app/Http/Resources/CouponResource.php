<?php

namespace App\Http\Resources;

use App\Enums\CouponScope;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Coupon
 */
class CouponResource extends JsonResource
{
    private const STATE_LABELS = ['active' => 'نشط', 'suspended' => 'موقوف', 'expired' => 'منتهي'];

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
            'code' => $this->code,
            'type' => $this->type->value,
            'value' => (float) $this->value,
            'scope' => $this->scope->value,
            'scope_label' => match ($this->scope) {
                CouponScope::AllCourses => 'كل الدورات',
                CouponScope::Workshops => 'الورش',
                CouponScope::Course => $this->course?->title ?? 'دورة محذوفة',
            },
            'course_id' => $this->course_id,
            'usage_limit' => $this->usage_limit,
            'times_used' => $this->times_used,
            'expires_on' => $this->expires_on->toDateString(),
            'is_active' => $this->is_active,
            'state' => $state,
            'state_label' => self::STATE_LABELS[$state],
        ];
    }
}
