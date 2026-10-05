<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Student;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The invoice for an order, emailed to the student, in the platform currency with the VAT included in the price
 * and the invoice note from the settings (and the bank details while a bank transfer is still awaited).
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
        $settings = app(PlatformSettings::class);
        $money = fn (float|string $amount): string => $settings->money(round((float) $amount, 2));

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

        $message->line('الإجمالي: '.$money($order->total));

        $vat = (float) $settings->get('vat_percent');
        if ($vat > 0) {
            // prices include VAT: total = net × (1 + rate)
            $message->line("منها ضريبة القيمة المضافة ({$vat}%): ".$money((float) $order->total - (float) $order->total / (1 + $vat / 100)));
        }

        $message->line('طريقة الدفع: '.$order->payment_method->label())
            ->line('الحالة: '.$order->status->label());

        if ($order->status === OrderStatus::Pending && $order->payment_method === PaymentMethod::BankTransfer && filled($settings->get('bank_transfer_instructions'))) {
            $message->line('لإتمام الدفع بالتحويل البنكي:')->line($settings->get('bank_transfer_instructions'));
        }

        if ($order->status === OrderStatus::Refunded) {
            $message->line('تم استرداد هذا الطلب في '.$order->refunded_at->toDateString().'.');
        }

        return $message->line($settings->get('invoice_note') ?: 'شكراً لثقتك بنا.');
    }
}
