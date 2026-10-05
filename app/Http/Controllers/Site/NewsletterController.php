<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsletterController extends Controller
{
    /**
     * The site's newsletter form. Subscribing again after unsubscribing turns the emails back on.
     * The answer is the same for a new or a known address, and for the hidden "website" bot trap.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        if (blank($validated['website'] ?? null)) {
            Subscriber::query()->updateOrCreate(['email' => Str::lower($validated['email'])], ['unsubscribed_at' => null]);
        }

        return response()->json(['message' => 'تم اشتراكك. يصلك كل مقال جديد على بريدك.'], 201);
    }
}
