<?php

namespace App\Actions\Orders;

use App\Enums\CourseStatus;
use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Order;
use App\Models\Student;
use App\Models\Workshop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates a pending order for a course, a workshop seat or a month of Pro, applying a coupon.
 *
 * The coupon's use is reserved here with one atomic UPDATE, so two buyers racing for its last use
 * can't both get it; FailOrder gives the use back if the payment never completes.
 */
class PlaceOrder
{
    /**
     * @param  Course|Workshop|null  $item  null for a month of Pro
     *
     * @throws ValidationException
     */
    public function handle(Student $student, OrderItemType $type, Course|Workshop|null $item, PaymentMethod $method, ?string $couponCode = null): Order
    {
        $this->ensurePurchasable($student, $type, $item);

        return DB::transaction(function () use ($student, $type, $item, $method, $couponCode): Order {
            $price = match ($type) {
                OrderItemType::ProMonth => (float) config('sales.pro_month_price'),
                default => (float) $item->price,
            };

            $coupon = filled($couponCode) ? $this->reserveCoupon(Str::upper(trim($couponCode)), $type, $item) : null;
            $discount = $coupon?->discountFor($price) ?? 0.0;

            $order = new Order;
            $order->student()->associate($student);
            $order->forceFill([
                'item_type' => $type,
                'item_id' => $item?->getKey(),
                'item_name' => $item?->title ?? 'اشتراك Pro شهري',
                'subtotal' => $price,
                'discount' => $discount,
                'total' => round($price - $discount, 2),
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'payment_method' => $method,
                'status' => OrderStatus::Pending,
            ])->save();

            return $order;
        });
    }

    /**
     * @throws ValidationException
     */
    private function ensurePurchasable(Student $student, OrderItemType $type, Course|Workshop|null $item): void
    {
        $error = match (true) {
            $student->isSuspended() => 'حساب الطالب موقوف.',
            $type === OrderItemType::ProMonth => $item === null ? null : 'اشتراك Pro لا يرتبط بدورة أو ورشة.',
            $type === OrderItemType::Course && ! $item instanceof Course => 'الدورة غير موجودة.',
            $type === OrderItemType::Workshop && ! $item instanceof Workshop => 'الورشة غير موجودة.',
            $item instanceof Course && $item->status !== CourseStatus::Published => 'الدورة غير منشورة.',
            $item instanceof Course && $student->courses()->whereKey($item->id)->exists() => 'الطالب مسجّل في هذه الدورة بالفعل.',
            $item instanceof Workshop && $item->hasEnded() => 'انتهت هذه الورشة.',
            $item instanceof Workshop && $item->seatsTaken() >= $item->seats => 'لا توجد مقاعد متاحة في هذه الورشة.',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['item' => $error]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function reserveCoupon(string $code, OrderItemType $type, Course|Workshop|null $item): Coupon
    {
        $coupon = Coupon::query()->where('code', $code)->first();

        $error = match (true) {
            $coupon === null => 'كود الخصم غير صحيح.',
            $coupon->isExpired() => 'انتهت صلاحية كود الخصم.',
            ! $coupon->is_active => 'كود الخصم موقوف.',
            ! $coupon->scope->covers($type, $item?->getKey(), $coupon->course_id) => 'كود الخصم لا ينطبق على هذا المنتج.',
            default => null,
        };

        // the limit check and the increment are one statement, so concurrent orders can't overshoot it
        if ($error === null) {
            $reserved = Coupon::query()
                ->whereKey($coupon->id)
                ->where(fn ($query) => $query->whereNull('usage_limit')->orWhereColumn('times_used', '<', 'usage_limit'))
                ->increment('times_used');

            $error = $reserved === 0 ? 'وصل كود الخصم إلى حد الاستخدام.' : null;
        }

        if ($error !== null) {
            throw ValidationException::withMessages(['coupon' => $error]);
        }

        return $coupon->refresh();
    }
}
