<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class TestEmailController extends Controller
{
    /**
     * Send a test email to the signed-in member right away (not queued), to check the mail settings.
     */
    public function store(Request $request): JsonResponse
    {
        $email = $request->user()->email;

        try {
            Mail::raw('هذه رسالة تجريبية من لوحة تحكم '.config('app.name').'. إعدادات البريد تعمل.', function (Message $message) use ($email): void {
                $message->to($email)->subject('رسالة تجريبية — '.config('app.name'));
            });
        } catch (Throwable $exception) {
            Log::warning('Test email failed', ['error' => $exception->getMessage()]);

            throw ValidationException::withMessages(['mail_host' => 'تعذّر الإرسال: '.mb_substr($exception->getMessage(), 0, 200)]);
        }

        return response()->json(['sent_to' => $email, 'mailer' => config('mail.default')]);
    }
}
