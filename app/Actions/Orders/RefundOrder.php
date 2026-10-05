<?php

namespace App\Actions\Orders;

use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
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
                // only the access this order gave: another paid order for the same course keeps its enrolment
                Enrollment::query()->where('order_id', $order->id)->delete();
            }

            if ($order->item_type === OrderItemType::ProMonth && $student->pro_until !== null) {
                $until = $student->pro_until->copy()->subDays(Student::PRO_PERIOD_DAYS);
                $student->forceFill(['pro_until' => $until->isFuture() ? $until : null])->save();
            }

            return $order;
        });
    }
}
