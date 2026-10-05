<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsletterUnsubscribeController extends Controller
{
    /**
     * The signed link at the bottom of every newsletter email: a page with one button, so a mail scanner
     * opening the link doesn't unsubscribe anyone by itself.
     */
    public function show(Request $request): View
    {
        return view('newsletter.unsubscribe', ['email' => $request->query('email'), 'done' => false]);
    }

    /**
     * Stops the new-article emails for this address, whether it subscribed on the site or is a student's.
     */
    public function store(Request $request): View
    {
        $email = Str::lower((string) $request->query('email'));

        Subscriber::query()->updateOrCreate(['email' => $email], ['unsubscribed_at' => now(), 'source' => 'unsubscribe']);

        return view('newsletter.unsubscribe', ['email' => $email, 'done' => true]);
    }
}
