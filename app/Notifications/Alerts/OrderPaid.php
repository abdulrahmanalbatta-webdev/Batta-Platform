<?php

namespace App\Notifications\Alerts;

use App\Enums\AlertType;
use App\Models\Order;

class OrderPaid extends TeamAlert
{
    public function __construct(public Order $order) {}

    public function type(): AlertType
    {
        return AlertType::Orders;
    }

    protected function title(): string
    {
        return 'طلب مكتمل: '.$this->order->item_name;
    }

    protected function meta(): string
    {
        return $this->order->student->name.' · '.$this->money((float) $this->order->total);
    }

    protected function page(): string
    {
        return 'orders';
    }

    protected function params(): array
    {
        return ['q' => $this->order->number()];
    }
}
