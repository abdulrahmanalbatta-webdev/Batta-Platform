<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\Site\AccountResource;
use App\Models\Student;
use App\Notifications\Alerts\StudentRegistered;
use App\Support\PlatformSettings;
use App\Support\TeamAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisteredStudentController extends Controller
{
    /**
     * Opens a student account and signs it in (returns a token), unless sign-ups are switched off in settings.
     * The team is alerted with the phone (WhatsApp) to arrange the payment by hand.
     * An address the platform already knows (e.g. a student the team added) sets its password with "forgot password".
     */
    public function store(Request $request, PlatformSettings $settings): JsonResponse
    {
        abort_unless($settings->get('registration_open'), 403, 'التسجيل مغلق حالياً.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(Student::class)],
            'phone' => ['required', 'string', 'regex:'.Student::PHONE_PATTERN],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'country' => ['nullable', 'string', 'max:60'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [
            'email.unique' => 'هذا البريد مسجّل مسبقاً. سجّل الدخول، أو استخدم "نسيت كلمة المرور" لتعيين كلمة مرور.',
            'phone.regex' => 'اكتب رقم واتساب صحيحاً مع مقدّمة الدولة، مثل ‎+970 59 000 0000.',
        ]);

        $student = new Student(['name' => $validated['name'], 'email' => $validated['email'], 'phone' => $validated['phone'], 'country' => $validated['country'] ?? null]);
        $student->password = $validated['password'];
        $student->last_active_at = now();
        $student->save();

        TeamAlerts::send(new StudentRegistered($student));

        return response()->json([
            'token' => StudentTokenController::issue($student, $request),
            'data' => new AccountResource($student),
        ], 201);
    }
}
