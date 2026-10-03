<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The invoice for an order, emailed to the student.
 */
class OrderInvoice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(Student $notifiable): MailMessage
    {
        $order = $this->order;
        $money = fn (string $amount): string => number_format((float) $amount, 2).'$';

        $message = (new MailMessage)
            ->subject('فاتورة الطلب '.$order->number().' — '.config('app.name'))
            ->greeting("مرحباً {$notifiable->name}!")
            ->line('رقم الطلب: '.$order->number())
            ->line('التاريخ: '.$order->created_at->toDateString())
            ->line("المنتج: {$order->item_name} ({$order->item_type->label()})")
            ->line('السعر: '.$money($order->subtotal));

        if ((float) $order->discount > 0) {
            $message->line("الخصم ({$order->coupon_code}): -".$money($order->discount));
        }

        $message->line('الإجمالي: '.$money($order->total))
            ->line('طريقة الدفع: '.$order->payment_method->label())
            ->line('الحالة: '.$order->status->label());

        if ($order->status === OrderStatus::Refunded) {
            $message->line('تم استرداد هذا الطلب في '.$order->refunded_at->toDateString().'.');
        }

        return $message->line('شكراً لثقتك بنا.');
    }
}
