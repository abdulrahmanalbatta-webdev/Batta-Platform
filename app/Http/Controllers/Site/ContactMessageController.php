<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContactMessageController extends Controller
{
    /**
     * What a student's message is about (the site's student form), shown above the message in the dashboard.
     */
    public const TOPICS = [
        'course' => 'سؤال عن دورة',
        'workshop' => 'سؤال عن ورشة',
        'account' => 'مشكلة في الحساب',
        'suggestion' => 'اقتراح',
        'other' => 'استفسار',
    ];

    /**
     * The contact form. Lands in the dashboard's messages (which alerts the team); replies go to the email given.
     * A signed-in student (Bearer token) writes into their own conversation, a visitor starts a new one.
     * The optional topic, and the course or workshop it is about, head the message so the team sees them at once.
     * The hidden "website" field is a bot trap: when it's filled, the answer looks the same but nothing is kept.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'topic' => ['nullable', 'string', Rule::in(array_keys(self::TOPICS))],
            'about' => ['nullable', 'string', 'max:160'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        if (filled($validated['website'] ?? null)) {
            return $this->received();
        }

        /** @var Student|null $student */
        $student = $request->user('sanctum');

        DB::transaction(function () use ($validated, $student): void {
            $conversation = $student
                ? Conversation::query()->firstOrCreate(['student_id' => $student->id], ['name' => $student->name, 'email' => $student->email])
                : Conversation::query()->create(['name' => $validated['name'], 'email' => $validated['email']]);

            $conversation->messages()->create(['from_contact' => true, 'body' => $this->body($validated)]);
        });

        return $this->received();
    }

    /**
     * The message, under a "topic: about" line when the student form sent one.
     *
     * @param  array{message: string, topic?: ?string, about?: ?string}  $validated
     */
    private function body(array $validated): string
    {
        $topic = isset($validated['topic']) ? self::TOPICS[$validated['topic']] : null;
        $heading = collect([$topic, filled($validated['about'] ?? null) ? trim($validated['about']) : null])->filter()->implode(': ');

        return $heading === '' ? $validated['message'] : "[{$heading}]\n\n{$validated['message']}";
    }

    private function received(): JsonResponse
    {
        return response()->json(['message' => 'وصلت رسالتك، سنرد عليك على بريدك قريباً.'], 201);
    }
}
