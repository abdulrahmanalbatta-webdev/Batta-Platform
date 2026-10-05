<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * The member's latest bell notifications and how many are unread.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $user->notifications()->limit(20)->get()->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                ...$notification->data,
                'unread' => $notification->read_at === null,
                'at' => $notification->created_at->toIso8601String(),
            ]),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }
}
