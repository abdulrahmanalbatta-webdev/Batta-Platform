<?php

namespace App\Notifications\Alerts;

use App\Enums\AlertType;
use App\Models\ConversationMessage;
use Illuminate\Support\Str;

class ContactMessageReceived extends TeamAlert
{
    public function __construct(public ConversationMessage $message) {}

    public function type(): AlertType
    {
        return AlertType::Messages;
    }

    protected function title(): string
    {
        return 'رسالة جديدة من '.$this->message->conversation->name;
    }

    protected function meta(): string
    {
        return Str::limit((string) ($this->message->body ?: $this->message->attachment_name), 80);
    }

    protected function page(): string
    {
        return 'messages';
    }

    protected function params(): array
    {
        return ['c' => $this->message->conversation_id];
    }
}
