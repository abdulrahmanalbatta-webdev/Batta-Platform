<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\Site\AccountResource;
use App\Models\Student;
use App\Notifications\Alerts\StudentRegistered;
use App\Support\PlatformSettings;
use App\Support\SocialAccounts;
use App\Support\TeamAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SocialLoginController extends Controller
{
    /**
     * Signs a student in with a Google or Facebook access token (returns a Bearer token like auth/login).
     * The account is found by the provider's user ID, else by its (provider-verified) email, which links it.
     * A new account also needs the phone (WhatsApp), like the sign-up form: without it the answer is a 422 on
     * "phone", and the site asks for it and sends the same token again.
     */
    public function store(Request $request, string $provider, SocialAccounts $accounts, PlatformSettings $settings): JsonResponse
    {
        abort_unless($accounts->enabled($provider), 404);

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'phone' => ['nullable', 'string', 'regex:'.Student::PHONE_PATTERN],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [
            'phone.regex' => 'اكتب رقم واتساب صحيحاً مع مقدّمة الدولة، مثل ‎+970 59 000 0000.',
        ]);

        $account = $accounts->identify($provider, $validated['token']);
        $column = $provider.'_id';

        $student = Student::query()->where($column, $account['id'])->first()
            ?? Student::query()->where('email', $account['email'])->first();

        if ($student !== null) {
            abort_if($student->isSuspended(), 403, 'هذا الحساب موقوف. تواصل معنا للمساعدة.');

            $student->forceFill([$column => $student->{$column} ?? $account['id'], 'last_active_at' => now()])->save();

            return response()->json(['token' => StudentTokenController::issue($student, $request), 'data' => new AccountResource($student)]);
        }

        abort_unless($settings->get('registration_open'), 403, 'التسجيل مغلق حالياً.');

        if (blank($validated['phone'] ?? null)) {
            throw ValidationException::withMessages(['phone' => 'أضف رقم واتساب لإكمال إنشاء حسابك، لنتواصل معك بخصوص الدورات.']);
        }

        $student = new Student(['name' => $account['name'], 'email' => $account['email'], 'phone' => $validated['phone']]);
        $student->forceFill([$column => $account['id'], 'last_active_at' => now()])->save();

        TeamAlerts::send(new StudentRegistered($student));

        return response()->json(['token' => StudentTokenController::issue($student, $request), 'data' => new AccountResource($student)], 201);
    }
}
