<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AvatarController extends Controller
{
    /**
     * Replace the signed-in member's profile photo.
     */
    public function store(Request $request): UserResource
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        $user = $request->user();
        $previous = $user->avatar_path;

        $user->forceFill(['avatar_path' => $request->file('avatar')->store('avatars', 'public')])->save();

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        return new UserResource($user);
    }
}
