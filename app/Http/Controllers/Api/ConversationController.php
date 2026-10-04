<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConversationController extends Controller
{
    /**
     * Every conversation, the most recent activity first, with its last message.
     */
    public function index(): AnonymousResourceCollection
    {
        return ConversationResource::collection(
            Conversation::query()->with(['lead:id,company', 'latestMessage'])->orderByDesc('last_message_at')->orderByDesc('id')->get(),
        );
    }

    /**
     * Open the conversation with a project request's client or a student, starting it if there is none yet.
     */
    public function store(Request $request): ConversationResource
    {
        $validated = $request->validate([
            'lead_id' => ['required_without:student_id', 'prohibits:student_id', 'integer', Rule::exists('leads', 'id')],
            'student_id' => ['required_without:lead_id', 'integer', Rule::exists('students', 'id')],
        ]);

        $contact = isset($validated['lead_id']) ? Lead::findOrFail($validated['lead_id']) : Student::findOrFail($validated['student_id']);
        $key = $contact instanceof Lead ? 'lead_id' : 'student_id';

        if (blank($contact->email)) {
            throw ValidationException::withMessages([$key => 'لا يوجد بريد إلكتروني لهذا العميل، أضفه أولاً.']);
        }

        $conversation = Conversation::query()->firstOrCreate([$key => $contact->id], [
            'name' => $contact->name,
            'email' => $contact->email,
            'read_at' => now(),
            'last_message_at' => now(),
        ]);

        return new ConversationResource($conversation->load(['lead:id,company', 'latestMessage']));
    }

    /**
     * A conversation with all its messages, oldest first.
     */
    public function show(Conversation $conversation): ConversationResource
    {
        return new ConversationResource($conversation->load([
            'lead:id,company',
            'latestMessage',
            'messages' => fn ($query) => $query->with('sender:id,name')->oldest()->oldest('id'),
        ]));
    }

    /**
     * Delete a conversation with its messages and their attachments.
     */
    public function destroy(Conversation $conversation): Response
    {
        $conversation->deleteWithAttachments();

        return response()->noContent();
    }
}
