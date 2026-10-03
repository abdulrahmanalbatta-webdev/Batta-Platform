<?php

namespace App\Actions\Orders;

use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Models\Enrollment;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a refund and takes back what the order gave: the course, the workshop seat or the Pro month.
 * The coupon use is not returned: it was spent on a real purchase.
 *
 * Until a payment gateway is connected, the money itself is returned outside the dashboard.
 */
class RefundOrder
{
    /**
     * @throws ValidationException
     */
    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== OrderStatus::Completed) {
                throw ValidationException::withMessages(['status' => 'يمكن استرداد الطلبات المكتملة فقط.']);
            }

            $order->forceFill(['status' => OrderStatus::Refunded, 'refunded_at' => now()])->save();

            $student = $order->student;

            if ($order->item_type === OrderItemType::Course) {
                Enrollment::query()->where('student_id', $student->id)->where('course_id', $order->item_id)->delete();
            }

            if ($order->item_type === OrderItemType::ProMonth && $student->pro_until !== null) {
                $until = $student->pro_until->copy()->subMonth();
                $student->forceFill(['pro_until' => $until->isFuture() ? $until : null])->save();
            }

            return $order;
        });
    }
}
