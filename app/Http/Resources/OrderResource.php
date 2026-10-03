<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
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
            'student' => [
                'id' => $this->student->id,
                'name' => $this->student->name,
                'initial' => $this->student->initial,
                'email' => $this->student->email,
            ],
            'item_type' => $this->item_type->value,
            'item_type_label' => $this->item_type->label(),
            'item_name' => $this->item_name,
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'fee' => (float) $this->fee,
            'coupon_code' => $this->coupon_code,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'date' => $this->created_at->toDateString(),
            'created_at' => $this->created_at->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
        ];
    }
}
