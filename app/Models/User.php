<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'title', 'phone', 'bio', 'github', 'linkedin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    /**
     * An invited member stays pending until they set their password from the invitation email.
     */
    public function isPending(): bool
    {
        return $this->email_verified_at === null;
    }

    /**
     * Sign the member out everywhere except the given session: delete their stored sessions and
     * rotate the remember token so "remember me" cookies on those devices stop working too.
     */
    public function endSessions(?string $exceptSessionId = null): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table'))
                ->where('user_id', $this->getKey())
                ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
                ->delete();
        }

        $this->forceFill(['remember_token' => Str::random(60)])->save();
    }

    /**
     * @return Attribute<string, never>
     */
    protected function initial(): Attribute
    {
        return Attribute::get(fn (): string => Str::substr(trim($this->name), 0, 1));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null);
    }
}
