<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationAttachmentController extends Controller
{
    /**
     * Download a message's attachment under its original name.
     */
    public function show(Conversation $conversation, ConversationMessage $message): StreamedResponse
    {
        abort_if($message->attachment_path === null, 404);

        return Storage::disk(ConversationMessage::attachmentDisk())->download($message->attachment_path, $message->attachment_name);
    }
}
