<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationReadController extends Controller
{
    /**
     * Mark one of the member's notifications as read (they clicked it).
     */
    public function store(Request $request, string $notification): Response
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return response()->noContent();
    }

    /**
     * "تعليم الكل كمقروء".
     */
    public function storeAll(Request $request): Response
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }
}
