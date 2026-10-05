<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\Site\AccountResource;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /**
     * The signed-in student.
     */
    public function show(Request $request): AccountResource
    {
        return new AccountResource($request->user());
    }

    /**
     * Name, country and email. Changing the email needs the current password.
     */
    public function update(Request $request): AccountResource
    {
        /** @var Student $student */
        $student = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(Student::class)->ignore($student->id)],
            'country' => ['nullable', 'string', 'max:60'],
            'current_password' => [Rule::requiredIf($request->input('email') !== $student->email), 'nullable', 'string', 'current_password:sanctum'],
        ], [
            'current_password.required' => 'أدخل كلمة المرور الحالية لتغيير البريد.',
            'current_password.current_password' => 'كلمة المرور الحالية غير صحيحة.',
        ]);

        $student->update($validated);

        return new AccountResource($student);
    }
}
