<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeamMemberRequest;
use App\Http\Requests\UpdateTeamMemberRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\TeamInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TeamMemberController extends Controller
{
    /**
     * The team, owner first, with the roles the viewer may hand out.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $members = User::query()->orderBy('created_at')->orderBy('id')->get()
            ->sortBy(fn (User $member): int => $member->role === Role::Owner ? 0 : 1)
            ->values();

        return UserResource::collection($members)->additional(['meta' => [
            'can_invite' => $request->user()->can('create', User::class),
            'roles' => collect($request->user()->role->assignableRoles())
                ->map(fn (Role $role): array => ['value' => $role->value, 'label' => $role->label()]),
        ]]);
    }

    /**
     * Invite a member: they get an email with a link to set their first password.
     */
    public function store(StoreTeamMemberRequest $request): UserResource
    {
        $member = new User($request->safe()->only(['name', 'email']));
        $member->password = Str::password(32);
        $member->role = $request->enum('role', Role::class);
        $member->save();

        $member->notify(new TeamInvitation(Password::broker('invitations')->createToken($member), $request->user()));

        return new UserResource($member);
    }

    /**
     * Change a member's role.
     */
    public function update(UpdateTeamMemberRequest $request, User $member): UserResource
    {
        $member->role = $request->enum('role', Role::class);
        $member->save();

        return new UserResource($member);
    }

    /**
     * Remove a member and sign them out of every device.
     */
    public function destroy(User $member): Response
    {
        Gate::authorize('delete', $member);

        $member->endSessions();

        if ($member->avatar_path) {
            Storage::disk('public')->delete($member->avatar_path);
        }

        $member->delete();

        return response()->noContent();
    }
}
