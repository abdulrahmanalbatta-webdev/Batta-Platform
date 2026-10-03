<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Change the signed-in member's password and sign out their other devices.
     */
    public function update(Request $request): Response
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        $user->update(['password' => $validated['password']]);
        $user->endSessions($request->session()->getId());

        return response()->noContent();
    }
}
