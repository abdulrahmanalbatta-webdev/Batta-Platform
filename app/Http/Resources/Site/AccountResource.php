<?php

namespace App\Http\Resources\Site;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in student's own account.
 *
 * @mixin Student
 */
class AccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'initial' => $this->initial,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->country,
            'joined' => $this->created_at->toDateString(),
        ];
    }
}
