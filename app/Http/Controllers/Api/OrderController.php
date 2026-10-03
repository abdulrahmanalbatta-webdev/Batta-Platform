<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    /**
     * Every order, newest first.
     */
    public function index(): AnonymousResourceCollection
    {
        return OrderResource::collection(Order::query()->with('student')->latest()->latest('id')->get());
    }
}
