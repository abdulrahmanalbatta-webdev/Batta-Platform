<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\Site\AccountResource;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class StudentTokenController extends Controller
{
    /**
     * Signs a student in: returns a Bearer token for the Authorization header (valid 30 days, config/sanctum.php).
     * A wrong email, a wrong password and an account without a password all get the same answer.
     */
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $student = Student::query()->where('email', Str::lower($credentials['email']))->first();

        if ($student?->password === null || ! Hash::check($credentials['password'], $student->password)) {
            throw ValidationException::withMessages(['email' => 'البريد أو كلمة المرور غير صحيحة.']);
        }

        abort_if($student->isSuspended(), 403, 'هذا الحساب موقوف. تواصل معنا للمساعدة.');

        $student->forceFill(['last_active_at' => now()])->save();

        return response()->json([
            'token' => self::issue($student, $request),
            'data' => new AccountResource($student),
        ]);
    }

    /**
     * Signs out this device (revokes the token the request came with).
     */
    public function destroy(Request $request): Response
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }

    /**
     * A new token named after the device ("device_name", else the browser's user agent).
     */
    public static function issue(Student $student, Request $request): string
    {
        $name = $request->input('device_name') ?: Str::limit((string) $request->userAgent(), 100, '') ?: 'site';

        return $student->createToken($name)->plainTextToken;
    }
}
