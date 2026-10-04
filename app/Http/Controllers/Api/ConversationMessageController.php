<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationMessageResource;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Notifications\ConversationReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ConversationMessageController extends Controller
{
    /**
     * Reply in a conversation, optionally with one file; the reply is emailed to the contact.
     */
    public function store(Request $request, Conversation $conversation): ConversationMessageResource
    {
        $validated = $request->validate([
            'body' => ['nullable', 'required_without:attachment', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'extensions:jpg,jpeg,png,gif,webp,pdf,zip,doc,docx,xls,xlsx,txt'],
        ], [
            'body.required_without' => 'اكتب رسالة أو أرفق ملفاً.',
        ], [
            'body' => 'الرسالة',
            'attachment' => 'المرفق',
        ]);

        $file = $request->file('attachment');

        $message = $conversation->messages()->create([
            'from_contact' => false,
            'user_id' => $request->user()->id,
            'body' => isset($validated['body']) ? trim($validated['body']) : null,
            'attachment_path' => $file?->store("conversations/{$conversation->id}", ConversationMessage::ATTACHMENT_DISK),
            'attachment_name' => $file === null ? null : Str::limit(basename($file->getClientOriginalName()), 200, ''),
        ]);

        $conversation->update(['read_at' => now(), 'last_message_at' => $message->created_at]);

        Notification::route('mail', [$conversation->email => $conversation->name])->notify(new ConversationReply($message));

        return new ConversationMessageResource($message->load('sender:id,name'));
    }
}
