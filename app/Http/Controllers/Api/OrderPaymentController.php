<?php

namespace App\Http\Controllers\Api;

use App\Actions\Orders\CompleteOrder;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;

class OrderPaymentController extends Controller
{
    /**
     * Confirm a pending order's payment by hand (e.g. a bank transfer that arrived).
     */
    public function store(Order $order, CompleteOrder $complete): OrderResource
    {
        return new OrderResource($complete->handle($order)->load('student'));
    }
}
