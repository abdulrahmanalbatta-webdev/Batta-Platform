<?php

namespace App\Actions\Orders;

use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Models\Enrollment;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marks a pending order as paid and gives the student what they bought: the course, the workshop seat
 * (a paid workshop order is the seat) or another month of Pro.
 */
class CompleteOrder
{
    /**
     * @param  float  $fee  what the payment gateway kept
     *
     * @throws ValidationException
     */
    public function handle(Order $order, float $fee = 0): Order
    {
        return DB::transaction(function () use ($order, $fee): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== OrderStatus::Pending) {
                throw ValidationException::withMessages(['status' => 'يمكن تأكيد دفع الطلبات المعلّقة فقط.']);
            }

            $order->forceFill(['status' => OrderStatus::Completed, 'paid_at' => now(), 'fee' => $fee])->save();

            $student = $order->student;

            match ($order->item_type) {
                OrderItemType::Course => Enrollment::query()->firstOrCreate(
                    ['student_id' => $student->id, 'course_id' => $order->item_id],
                    ['order_id' => $order->id],
                ),
                OrderItemType::ProMonth => $student->forceFill([
                    'pro_until' => ($student->isPro() ? $student->pro_until : now())->copy()->addMonth(),
                ])->save(),
                OrderItemType::Workshop => null,
            };

            return $order;
        });
    }
}
