<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Notifications\OrderInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OrderInvoiceController extends Controller
{
    /**
     * Email the order's invoice to the student (not for failed orders: nothing was paid).
     *
     * @throws ValidationException
     */
    public function store(Order $order): JsonResponse
    {
        if ($order->status === OrderStatus::Failed) {
            throw ValidationException::withMessages(['status' => 'لا توجد فاتورة لطلب فشل دفعه.']);
        }

        $order->student->notify(new OrderInvoice($order));

        return response()->json(['message' => 'تم إرسال الفاتورة إلى '.$order->student->email]);
    }
}
