<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'initial' => $this->initial,
            'avatar_url' => $this->avatar_url,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'title' => $this->title,
            'phone' => $this->phone,
            'bio' => $this->bio,
            'github' => $this->github,
            'linkedin' => $this->linkedin,
            'pending' => $this->isPending(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'is_you' => $viewer?->is($this->resource) ?? false,
            'can' => [
                'update' => $viewer?->can('update', $this->resource) ?? false,
                'delete' => $viewer?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
