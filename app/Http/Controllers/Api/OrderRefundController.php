<?php

namespace App\Http\Controllers\Api;

use App\Actions\Orders\RefundOrder;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;

class OrderRefundController extends Controller
{
    /**
     * Record a refund and take back what the order gave.
     */
    public function store(Order $order, RefundOrder $refund): OrderResource
    {
        return new OrderResource($refund->handle($order)->load('student'));
    }
}
