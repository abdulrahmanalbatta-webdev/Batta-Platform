<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Notifications\EmailChanged;
use Illuminate\Support\Facades\Notification;

class ProfileController extends Controller
{
    /**
     * Update the signed-in member's own details.
     */
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $previousEmail = $user->email;

        $user->update($request->safe()->except('current_password'));

        if ($user->wasChanged('email')) {
            // the old address hears about it, in case it wasn't them
            Notification::route('mail', $previousEmail)->notify(new EmailChanged($user->name, $user->email));
        }

        return new UserResource($user);
    }
}
