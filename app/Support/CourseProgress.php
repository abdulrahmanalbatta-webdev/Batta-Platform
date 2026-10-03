<?php

namespace App\Support;

use App\Models\Enrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Course progress from lesson completions: finished lessons of a course ÷ lessons in that course.
 *
 * Computed with three grouped queries for any number of students, instead of per student.
 */
class CourseProgress
{
    /**
     * @param  Collection<int, int>|array<int, int>  $studentIds
     * @return array<int, array<int, int>> student id => [course id => percent]
     */
    public function forStudents(Collection|array $studentIds): array
    {
        $studentIds = collect($studentIds)->values();

        if ($studentIds->isEmpty()) {
            return [];
        }

        $enrollments = Enrollment::query()->whereIn('student_id', $studentIds)->get(['student_id', 'course_id']);

        $lessonsPerCourse = DB::table('lessons')
            ->join('course_modules', 'course_modules.id', '=', 'lessons.course_module_id')
            ->whereIn('course_modules.course_id', $enrollments->pluck('course_id')->unique())
            ->groupBy('course_modules.course_id')
            ->pluck(DB::raw('count(*)'), 'course_modules.course_id');

        $completed = DB::table('lesson_completions')
            ->join('lessons', 'lessons.id', '=', 'lesson_completions.lesson_id')
            ->join('course_modules', 'course_modules.id', '=', 'lessons.course_module_id')
            ->whereIn('lesson_completions.student_id', $studentIds)
            ->groupBy('lesson_completions.student_id', 'course_modules.course_id')
            ->get(['lesson_completions.student_id', 'course_modules.course_id', DB::raw('count(*) as done')])
            ->keyBy(fn (object $row): string => $row->student_id.':'.$row->course_id);

        $progress = [];

        foreach ($enrollments as $enrollment) {
            $total = (int) ($lessonsPerCourse[$enrollment->course_id] ?? 0);
            $done = (int) ($completed[$enrollment->student_id.':'.$enrollment->course_id]->done ?? 0);
            $progress[$enrollment->student_id][$enrollment->course_id] = $total > 0 ? (int) round(min($done, $total) / $total * 100) : 0;
        }

        return $progress;
    }

    /**
     * A student's overall progress: the average over their courses (0 without courses).
     *
     * @param  array<int, int>  $perCourse
     */
    public static function average(array $perCourse): int
    {
        return $perCourse === [] ? 0 : (int) round(array_sum($perCourse) / count($perCourse));
    }
}
