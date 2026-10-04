<?php

namespace App\Notifications\Alerts;

use App\Enums\AlertType;
use App\Models\Review;

class ReviewSubmitted extends TeamAlert
{
    public function __construct(public Review $review) {}

    public function type(): AlertType
    {
        return AlertType::Reviews;
    }

    protected function title(): string
    {
        return 'تقييم جديد بانتظار المراجعة';
    }

    protected function meta(): string
    {
        return $this->review->rating.' ★ · '.$this->review->course->title;
    }

    protected function page(): string
    {
        return 'reviews';
    }
}
