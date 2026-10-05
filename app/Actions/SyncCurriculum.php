<?php

namespace App\Actions;

use App\Models\Course;
use App\Models\CourseModule;
use App\Support\Duration;
use Illuminate\Support\Facades\DB;

/**
 * Saves the curriculum editor's modules and lessons onto a course.
 *
 * Rows sent with an id are updated in place (so later lesson progress stays attached), rows without
 * one are created, and rows missing from the list are deleted. An id that doesn't belong to this
 * course (or, for a lesson, to that module) is treated as a new row, never as someone else's record.
 */
class SyncCurriculum
{
    /**
     * @param  list<array{id?: int|null, title?: string|null, lessons?: list<array{id?: int|null, title?: string|null, duration?: string|null}>}>  $modules
     */
    public function handle(Course $course, array $modules): void
    {
        DB::transaction(function () use ($course, $modules): void {
            $existing = $course->modules()->with('lessons')->get()->keyBy('id');
            $kept = [];

            foreach (array_values($modules) as $position => $input) {
                /** @var CourseModule $module */
                $module = $existing->get($input['id'] ?? null) ?? $course->modules()->make();
                $module->fill(['title' => $input['title'] ?? null, 'position' => $position])->save();
                $kept[] = $module->id;

                $this->syncLessons($module, $input['lessons'] ?? []);
            }

            $course->modules()->whereNotIn('id', $kept)->delete();
        });
    }

    /**
     * @param  list<array{id?: int|null, title?: string|null, duration?: string|null}>  $lessons
     */
    private function syncLessons(CourseModule $module, array $lessons): void
    {
        $existing = $module->lessons->keyBy('id');
        $kept = [];

        foreach (array_values($lessons) as $position => $input) {
            $lesson = $existing->get($input['id'] ?? null) ?? $module->lessons()->make();
            $lesson->fill([
                'title' => $input['title'] ?? null,
                'duration_seconds' => Duration::toSeconds($input['duration'] ?? null),
                'position' => $position,
            ])->save();
            $kept[] = $lesson->id;
        }

        $module->lessons()->whereNotIn('id', $kept)->delete();
    }
}
