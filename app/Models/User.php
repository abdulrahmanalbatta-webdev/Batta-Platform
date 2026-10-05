<?php

namespace App\Models;

use App\Enums\AlertType;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use App\Models\Concerns\LogsActivity;
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
#[Hidden(['password', 'remember_token', 'known_devices'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

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
            'notification_preferences' => 'array',
            'known_devices' => 'array',
        ];
    }

    /**
     * Whether this alert also reaches the member by email (the settings → notifications switches).
     */
    public function wantsEmailFor(AlertType $type): bool
    {
        return (bool) ($this->notification_preferences[$type->value] ?? $type->emailsByDefault());
    }

    /**
     * The email switch of every alert type this member's role receives.
     *
     * @return array<string, bool>
     */
    public function emailPreferences(): array
    {
        return collect(AlertType::cases())
            ->filter(fn (AlertType $type): bool => $type->isFor($this->role))
            ->mapWithKeys(fn (AlertType $type): array => [$type->value => $this->wantsEmailFor($type)])
            ->all();
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

    public function activityLabel(): string
    {
        return 'العضو';
    }

    protected function activityStateAttribute(): ?string
    {
        return 'role';
    }

    protected function activityStateLabel(): ?string
    {
        return $this->role->label();
    }

    /**
     * @return list<string>
     */
    protected function activityIgnoredAttributes(): array
    {
        return ['password', 'remember_token', 'last_login_at', 'avatar_path', 'title', 'phone', 'bio', 'github', 'linkedin', 'notification_preferences', 'known_devices', 'email_verified_at'];
    }
}
