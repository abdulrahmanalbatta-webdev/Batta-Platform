<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentStatusController extends Controller
{
    /**
     * Suspend or reactivate one or more students (the list's row action and its bulk bar).
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['integer', 'distinct'],
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);

        $suspend = $validated['status'] === 'suspended';

        // one save per student, so each change lands in the activity log
        $students = DB::transaction(fn () => Student::query()
            ->whereKey($validated['ids'])
            ->when($suspend, fn ($query) => $query->whereNull('suspended_at'), fn ($query) => $query->whereNotNull('suspended_at'))
            ->get()
            ->each(fn (Student $student) => $student->forceFill(['suspended_at' => $suspend ? now() : null])->save()));

        return response()->json(['updated' => $students->count()]);
    }
}
