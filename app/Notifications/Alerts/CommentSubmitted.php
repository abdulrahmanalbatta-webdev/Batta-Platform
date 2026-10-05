<?php

namespace App\Notifications\Alerts;

use App\Enums\AlertType;
use App\Models\ArticleComment;

class CommentSubmitted extends TeamAlert
{
    public function __construct(public ArticleComment $comment) {}

    public function type(): AlertType
    {
        return AlertType::Comments;
    }

    protected function title(): string
    {
        return 'تعليق جديد بانتظار المراجعة';
    }

    protected function meta(): string
    {
        return $this->comment->student->name.' · '.$this->comment->article->title;
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
