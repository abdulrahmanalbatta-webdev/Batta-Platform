<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    /**
     * Email a reset link. The reply is the same whether or not the address has an account, so it can't be used to find members.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => __('passwords.sent')]);
    }
}
