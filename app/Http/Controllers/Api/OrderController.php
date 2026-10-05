<?php

namespace App\Http\Controllers\Api;

use App\Actions\Orders\CompleteOrder;
use App\Actions\Orders\PlaceOrder;
use App\Enums\OrderItemType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Course;
use App\Models\Order;
use App\Models\Student;
use App\Models\Workshop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /**
     * Every order, newest first.
     */
    public function index(): AnonymousResourceCollection
    {
        return OrderResource::collection(Order::query()->with('student')->latest()->latest('id')->get());
    }

    /**
     * A manual order: the team took the payment by hand (transfer, wallet or cash) and records it here.
     * "paid" completes it at once, which enrols the student (or books the seat, or adds a month of Pro);
     * without it the order waits as pending until the payment is confirmed.
     */
    public function store(Request $request, PlaceOrder $place, CompleteOrder $complete): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists(Student::class, 'id')],
            'item_type' => ['required', Rule::enum(OrderItemType::class)],
            'item_id' => ['required_unless:item_type,'.OrderItemType::ProMonth->value, 'nullable', 'integer'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'coupon' => ['nullable', 'string', 'max:40'],
            'paid' => ['sometimes', 'boolean'],
        ]);

        $type = OrderItemType::from($validated['item_type']);
        $item = match ($type) {
            OrderItemType::Course => Course::query()->find($validated['item_id']),
            OrderItemType::Workshop => Workshop::query()->find($validated['item_id']),
            OrderItemType::ProMonth => null,
        };
        abort_if($type !== OrderItemType::ProMonth && $item === null, 422, 'العنصر المختار غير موجود.');

        $order = DB::transaction(function () use ($validated, $type, $item, $place, $complete, $request): Order {
            $order = $place->handle(Student::query()->findOrFail($validated['student_id']), $type, $item, PaymentMethod::from($validated['payment_method']), $validated['coupon'] ?? null);

            return $request->boolean('paid', true) ? $complete->handle($order) : $order;
        });

        return (new OrderResource($order->load('student')))->response()->setStatusCode(201);
    }
}
