<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Notifications\StudentMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class StudentMessageController extends Controller
{
    /**
     * Email the chosen students (queued; suspended students are skipped).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:5000'],
            'ids.*' => ['integer', 'distinct'],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
        ], [], ['subject' => 'الموضوع', 'body' => 'الرسالة']);

        $students = Student::query()->whereKey($validated['ids'])->whereNull('suspended_at')->get();

        Notification::send($students, new StudentMessage($validated['subject'], $validated['body']));

        return response()->json(['sent' => $students->count()]);
    }
}
