<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Student;
use App\Support\CourseProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonCompletionController extends Controller
{
    /**
     * Marks a lesson as finished (again is harmless) and returns the course's new progress.
     */
    public function store(Request $request, Lesson $lesson, CourseProgress $progress): JsonResponse
    {
        $student = $this->student($request, $lesson);

        $student->completions()->firstOrCreate(['lesson_id' => $lesson->id], ['completed_at' => now()]);
        $student->forceFill(['last_active_at' => now()])->save();

        return $this->progress($student, $lesson, $progress);
    }

    /**
     * Un-marks a finished lesson.
     */
    public function destroy(Request $request, Lesson $lesson, CourseProgress $progress): JsonResponse
    {
        $student = $this->student($request, $lesson);

        $student->completions()->where('lesson_id', $lesson->id)->delete();

        return $this->progress($student, $lesson, $progress);
    }

    private function student(Request $request, Lesson $lesson): Student
    {
        /** @var Student $student */
        $student = $request->user();

        abort_unless($student->canAccess($lesson->module->course), 403, 'هذه الدورة غير متاحة في حسابك.');

        return $student;
    }

    private function progress(Student $student, Lesson $lesson, CourseProgress $progress): JsonResponse
    {
        $courseId = $lesson->module->course_id;
        $course = $progress->forStudent($student->id, [$courseId])[$courseId];

        return response()->json(['data' => ['progress' => $course['percent'], 'completed_lessons' => $course['completed']]]);
    }
}
