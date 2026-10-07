<?php

namespace App\Notifications\Alerts;

use App\Enums\AlertType;
use App\Models\Comment;

class CommentSubmitted extends TeamAlert
{
    public function __construct(public Comment $comment) {}

    public function type(): AlertType
    {
        return AlertType::Comments;
    }

    protected function title(): string
    {
        return $this->comment->parent_id ? 'رد جديد بانتظار المراجعة' : 'تعليق جديد بانتظار المراجعة';
    }

    protected function meta(): string
    {
        return $this->comment->student->name.' · '.$this->comment->commentable?->title;
    }

    protected function page(): string
    {
        return 'comments';
    }

    /**
     * @return array<string, int|string>
     */
    protected function params(): array
    {
        return ['status' => 'pending'];
    }
}
