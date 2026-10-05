<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class StudentPasswordResetLinkController extends Controller
{
    /**
     * Emails a reset link (it opens the site's /reset-password page). The answer is the same whether or not
     * the address has an account, so the form can't be used to find out who studies here.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'string', 'email']]);

        Password::broker('students')->sendResetLink(['email' => Str::lower($validated['email'])]);

        return response()->json(['message' => 'إذا كان البريد مسجّلاً لدينا، ستصلك رسالة فيها رابط تعيين كلمة المرور.']);
    }
}
