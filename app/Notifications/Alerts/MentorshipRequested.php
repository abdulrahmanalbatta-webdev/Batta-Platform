<?php

namespace App\Notifications\Alerts;

use App\Enums\AlertType;
use App\Models\MentorshipRequest;

/**
 * A student applied to the mentorship, or sent the payment receipt for an accepted request.
 */
class MentorshipRequested extends TeamAlert
{
    public function __construct(public MentorshipRequest $request, public bool $receipt = false) {}

    public function type(): AlertType
    {
        return AlertType::Mentorship;
    }

    protected function title(): string
    {
        return $this->receipt
            ? 'إشعار تحويل بانتظار المراجعة: '.$this->request->number()
            : 'طلب إرشاد جديد: '.$this->request->package_name;
    }

    protected function meta(): string
    {
        $student = $this->request->student;

        return collect([$student->name, $this->receipt ? "{$this->request->price} ₪" : null, $student->email])->filter()->implode(' · ');
    }

    protected function page(): string
    {
        return 'mentorship';
    }

    protected function params(): array
    {
        return ['request' => $this->request->id];
    }
}
