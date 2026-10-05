<?php

namespace App\Http\Controllers\Api;

use App\Actions\Orders\FailOrder;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;

class OrderFailureController extends Controller
{
    /**
     * Close a pending order whose payment never arrived (gives its coupon use back).
     */
    public function store(Order $order, FailOrder $fail): OrderResource
    {
        return new OrderResource($fail->handle($order)->load('student'));
    }
}
