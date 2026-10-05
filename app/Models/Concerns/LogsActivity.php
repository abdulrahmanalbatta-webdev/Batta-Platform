<?php

namespace App\Models\Concerns;

use App\Models\Activity;
use Illuminate\Support\Facades\Auth;

/**
 * Writes what a signed-in member creates, changes or deletes to the activity log. Changes without a member
 * (the scheduler, seeders, the public site) are not logged.
 */
trait LogsActivity
{
    /**
     * Attributes whose changes never make an entry on their own.
     */
    private const QUIET_ATTRIBUTES = ['created_at', 'updated_at', 'position', 'times_used', 'read_at', 'last_message_at', 'last_active_at'];

    public static function bootLogsActivity(): void
    {
        static::created(fn (self $model) => $model->logActivity('created'));
        static::updated(fn (self $model) => $model->logActivity('updated'));
        static::deleted(fn (self $model) => $model->logActivity('deleted'));
    }

    /**
     * What this is called in the log, e.g. "الدورة".
     */
    abstract public function activityLabel(): string;

    /**
     * How this record is named in the log.
     */
    public function activityName(): string
    {
        return (string) ($this->title ?? $this->name ?? '#'.$this->getKey());
    }

    /**
     * The attribute that holds this record's state (status, stage…); a change to it is logged as a new state.
     */
    protected function activityStateAttribute(): ?string
    {
        return null;
    }

    /**
     * The label of the current state, e.g. "منشورة".
     */
    protected function activityStateLabel(): ?string
    {
        return null;
    }

    /**
     * Further attributes that don't make an entry on their own.
     *
     * @return list<string>
     */
    protected function activityIgnoredAttributes(): array
    {
        return [];
    }

    protected function logActivity(string $action): void
    {
        // team members only: a student acting through the site API (Sanctum) is never the author
        if (! Auth::guard('web')->check()) {
            return;
        }

        $properties = null;

        if ($action === 'updated') {
            $changed = array_values(array_diff(array_keys($this->getChanges()), self::QUIET_ATTRIBUTES, $this->activityIgnoredAttributes()));

            if ($changed === []) {
                return;
            }

            $properties = ['changed' => $changed];
            $state = $this->activityStateAttribute();

            if ($state !== null && in_array($state, $changed, true)) {
                $action = 'status';
                $properties['state'] = $this->activityStateLabel();
            }
        }

        Activity::create([
            // a member deleting their own account can't be the author of the entry
            'user_id' => $action === 'deleted' && $this->is(Auth::guard('web')->user()) ? null : Auth::guard('web')->id(),
            'action' => $action,
            'subject_type' => $this->getMorphClass(),
            'subject_id' => $this->getKey(),
            'subject_name' => mb_substr($this->activityName(), 0, 255),
            'properties' => $properties,
        ]);
    }
}
