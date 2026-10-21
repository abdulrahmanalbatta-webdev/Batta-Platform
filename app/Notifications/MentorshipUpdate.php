<?php

namespace App\Notifications;

use App\Models\MentorshipRequest;
use App\Models\Student;
use App\Notifications\Concerns\RetriesDelivery;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the student where their mentorship request stands, at each step: received, accepted (pay now),
 * rejected, receipt turned down, payment confirmed, session booked, payment window closed.
 */
class MentorshipUpdate extends Notification implements ShouldQueue
{
    use Queueable, RetriesDelivery;

    public const EVENTS = ['received', 'accepted', 'rejected', 'proof_rejected', 'paid', 'scheduled', 'expired'];

    /**
     * Skip the email if the request is deleted before the queue sends it.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public MentorshipRequest $request, public string $event) {}

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
        $request = $this->request;
        $mail = (new MailMessage)->greeting("مرحباً {$notifiable->name}!");
        $number = "\u{2066}{$request->number()}\u{2069}";

        $mail = match ($this->event) {
            'received' => $mail->subject('وصل طلبك للإرشاد')
                ->line("وصلني طلبك للانضمام إلى {$request->package_name} (رقم الطلب {$number}).")
                ->line('سأراجعه وأردّ عليك خلال يومين.'),
            'accepted' => $mail->subject('تم قبول طلبك للإرشاد')
                ->line("يسعدني قبول طلبك للانضمام إلى {$request->package_name}.")
                ->line("المبلغ: {$request->price} ₪، ويُكتب رقم الطلب {$number} في ملاحظة التحويل.")
                ->line('حوّل المبلغ عبر إحدى المحافظ الظاهرة في صفحة طلبك، ثم ارفع إشعار التحويل قبل '.$this->time($request->payment_due_at).'.')
                ->line('إن لم يصل الإشعار قبل هذا الموعد يُلغى الطلب ويعود المقعد متاحاً.'),
            'rejected' => $mail->subject('بخصوص طلبك للإرشاد')
                ->line('شكراً لاهتمامك، لكن لا يمكنني قبول طلبك في الوقت الحالي.')
                ->lineIf(filled($request->decision_note), (string) $request->decision_note),
            'proof_rejected' => $mail->subject('نحتاج إشعار تحويل جديداً')
                ->line('راجعت إشعار التحويل الذي أرسلته ولم أتمكن من تأكيده.')
                ->lineIf(filled($request->decision_note), 'السبب: '.$request->decision_note)
                ->line('ارفع إشعاراً جديداً من صفحة طلبك قبل '.$this->time($request->payment_due_at).'.'),
            'paid' => $mail->subject('تم تأكيد الدفع')
                ->line("وصلني المبلغ وتأكّد اشتراكك في {$request->package_name}.")
                ->line('سأحدّد معك موعد الجلسة الأولى قريباً، وسيصلك بريد بالموعد ورابط اللقاء.'),
            'scheduled' => $mail->subject('موعد جلستك')
                ->line('موعد جلستك: '.$this->time($request->session_at).' (بتوقيت فلسطين).')
                ->lineIf(filled($request->meeting_url), 'رابط اللقاء: '.$request->meeting_url),
            'expired' => $mail->subject('انتهت مهلة الدفع')
                ->line("انتهت مهلة رفع إشعار التحويل لطلبك {$number}، فأُلغي الطلب وعاد المقعد متاحاً.")
                ->line('يمكنك تقديم طلب جديد من صفحة الإرشاد متى شئت.'),
        };

        return $mail->action('صفحة طلبك', $this->siteUrl());
    }

    /**
     * A time in the students' timezone, e.g. "2026-10-22 19:00".
     */
    private function time(?\DateTimeInterface $time): string
    {
        return $time === null ? '' : "\u{2066}".$time->copy()->setTimezone(MentorshipRequest::TIMEZONE)->format('Y-m-d H:i')."\u{2069}";
    }

    private function siteUrl(): string
    {
        return rtrim((string) app(PlatformSettings::class)->get('site_url'), '/').'/my-mentorship';
    }
}
