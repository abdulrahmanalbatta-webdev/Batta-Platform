<?php

namespace App\Actions\Orders;

use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use App\Models\Workshop;
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

            $student = $order->student;
            $this->ensureStillAvailable($order);

            $order->forceFill(['status' => OrderStatus::Completed, 'paid_at' => now(), 'fee' => $fee])->save();

            match ($order->item_type) {
                OrderItemType::Course => Enrollment::query()->create(['student_id' => $student->id, 'course_id' => $order->item_id, 'order_id' => $order->id]),
                OrderItemType::ProMonth => $student->forceFill([
                    'pro_until' => ($student->isPro() ? $student->pro_until : now())->copy()->addDays(Student::PRO_PERIOD_DAYS),
                ])->save(),
                OrderItemType::Workshop => null,
            };

            return $order;
        });
    }

    /**
     * Two pending orders can race for the same thing: the last workshop seat (counted under a lock, since a paid
     * workshop order is the seat), or a course the student already got through another order.
     *
     * @throws ValidationException
     */
    private function ensureStillAvailable(Order $order): void
    {
        if ($order->item_type === OrderItemType::Workshop) {
            $workshop = Workshop::query()->lockForUpdate()->find($order->item_id);

            if ($workshop !== null && $workshop->registrations()->count() >= $workshop->seats) {
                throw ValidationException::withMessages(['status' => 'اكتملت مقاعد الورشة؛ أغلق هذا الطلب كفاشل.']);
            }
        }

        if ($order->item_type === OrderItemType::Course
            && Enrollment::query()->where('student_id', $order->student_id)->where('course_id', $order->item_id)->exists()) {
            throw ValidationException::withMessages(['status' => 'الطالب مشترك بهذه الدورة من طلب آخر؛ أغلق هذا الطلب كفاشل.']);
        }
    }
}
