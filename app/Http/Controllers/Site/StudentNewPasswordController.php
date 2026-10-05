<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class StudentNewPasswordController extends Controller
{
    /**
     * Sets a new password from the emailed link and signs the student out of every device.
     * An unknown address gets the same "invalid link" answer as a wrong or expired token.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker('students')->reset(
            ['email' => Str::lower($validated['email']), 'token' => $validated['token'], 'password' => $validated['password']],
            function (Student $student, string $password): void {
                $student->password = $password;
                $student->save();
                $student->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'رابط تعيين كلمة المرور غير صالح أو انتهت صلاحيته. اطلب رابطاً جديداً.']);
        }

        return response()->json(['message' => 'تم تعيين كلمة المرور. سجّل الدخول بها الآن.']);
    }
}
