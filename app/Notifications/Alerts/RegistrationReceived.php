<?php

namespace App\Notifications\Alerts;

use App\Enums\AlertType;
use App\Models\Course;
use App\Models\Student;
use App\Models\Workshop;

/**
 * A student registered in a course or a workshop from the site.
 */
class RegistrationReceived extends TeamAlert
{
    public function __construct(public Student $student, public Course|Workshop $item) {}

    public function type(): AlertType
    {
        return AlertType::Registrations;
    }

    protected function title(): string
    {
        return ($this->item instanceof Course ? 'تسجيل جديد في الدورة: ' : 'تسجيل جديد في الورشة: ').$this->item->title;
    }

    protected function meta(): string
    {
        return collect([$this->student->name, $this->student->phone ? "\u{2066}{$this->student->phone}\u{2069}" : null, $this->student->email])->filter()->implode(' · ');
    }

    protected function page(): string
    {
        return 'students';
    }

    protected function params(): array
    {
        return ['q' => $this->student->email];
    }
}
