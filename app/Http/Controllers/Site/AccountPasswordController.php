<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AccountPasswordController extends Controller
{
    /**
     * Changes the password and signs out every other device (this one keeps its token).
     */
    public function update(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], [
            'current_password.current_password' => 'كلمة المرور الحالية غير صحيحة.',
        ]);

        $student->password = $validated['password'];
        $student->save();
        $student->tokens()->whereKeyNot($student->currentAccessToken()->getKey())->delete();

        return response()->json(['message' => 'تم تغيير كلمة المرور.']);
    }
}
