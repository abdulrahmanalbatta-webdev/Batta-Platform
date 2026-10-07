<?php

namespace App\Notifications\Alerts;

use App\Enums\AlertType;
use App\Models\Student;

/**
 * A new account on the site.
 */
class StudentRegistered extends TeamAlert
{
    public function __construct(public Student $student) {}

    public function type(): AlertType
    {
        return AlertType::Students;
    }

    protected function title(): string
    {
        return 'طالب جديد سجّل في الموقع: '.$this->student->name;
    }

    protected function meta(): string
    {
        return collect([$this->student->phone ? "\u{2066}{$this->student->phone}\u{2069}" : null, $this->student->email, $this->student->country])->filter()->implode(' · ');
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
