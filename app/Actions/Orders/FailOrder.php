<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marks a pending order as failed (payment declined or abandoned) and gives back its coupon use.
 */
class FailOrder
{
    /**
     * @throws ValidationException
     */
    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== OrderStatus::Pending) {
                throw ValidationException::withMessages(['status' => 'يمكن تحويل الطلبات المعلّقة فقط إلى فاشلة.']);
            }

            $order->forceFill(['status' => OrderStatus::Failed])->save();

            if ($order->coupon_id !== null) {
                Coupon::query()->whereKey($order->coupon_id)->where('times_used', '>', 0)->decrement('times_used');
            }

            return $order;
        });
    }
}
