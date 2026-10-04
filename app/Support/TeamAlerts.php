<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\Alerts\TeamAlert;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

/**
 * Sends a TeamAlert to the members whose role acts on it, except the member who caused it.
 */
class TeamAlerts
{
    public static function send(TeamAlert $alert): void
    {
        Notification::send(self::recipients($alert), $alert);
    }

    /**
     * Active members whose role receives this alert, without the signed-in member.
     *
     * @return Collection<int, User>
     */
    public static function recipients(TeamAlert $alert): Collection
    {
        return User::query()
            ->whereIn('role', $alert->type()->roles())
            ->whereNotNull('email_verified_at')
            ->when(Auth::id(), fn ($query, int|string $id) => $query->whereKeyNot($id))
            ->get();
    }
}
