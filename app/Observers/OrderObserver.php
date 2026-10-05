<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\Alerts\OrderPaid;
use App\Support\TeamAlerts;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class OrderObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Tell the sales team when an order is paid (CompleteOrder, today by hand and later from the payment gateway).
     */
    public function updated(Order $order): void
    {
        if ($order->wasChanged('status') && $order->status === OrderStatus::Completed) {
            TeamAlerts::send(new OrderPaid($order));
        }
    }
}
