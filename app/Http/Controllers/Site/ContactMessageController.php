<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactMessageController extends Controller
{
    /**
     * The contact form. Lands in the dashboard's messages (which alerts the team); replies go to the email given.
     * A signed-in student (Bearer token) writes into their own conversation, a visitor starts a new one.
     * The hidden "website" field is a bot trap: when it's filled, the answer looks the same but nothing is kept.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
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

            $conversation->messages()->create(['from_contact' => true, 'body' => $validated['message']]);
        });

        return $this->received();
    }

    private function received(): JsonResponse
    {
        return response()->json(['message' => 'وصلت رسالتك، سنرد عليك على بريدك قريباً.'], 201);
    }
}
