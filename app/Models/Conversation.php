<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['student_id', 'lead_id', 'name', 'email', 'read_at', 'last_message_at'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return HasMany<ConversationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    /**
     * @return HasOne<ConversationMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class)->latestOfMany();
    }

    /**
     * Delete the conversation, its messages (by the foreign key) and their attachment files.
     */
    public function deleteWithAttachments(): void
    {
        $paths = $this->messages()->whereNotNull('attachment_path')->pluck('attachment_path')->all();

        $this->delete();

        Storage::disk(ConversationMessage::ATTACHMENT_DISK)->delete($paths);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * Who the contact is, e.g. "طالب" or "عميل · محمصة البن".
     */
    public function contactLabel(): string
    {
        return match (true) {
            $this->student_id !== null => 'طالب',
            $this->lead_id !== null => 'عميل'.($this->lead?->company ? ' · '.$this->lead->company : ''),
            default => 'زائر',
        };
    }

    /**
     * @return Attribute<string, never>
     */
    protected function initial(): Attribute
    {
        return Attribute::get(fn (): string => Str::substr(trim($this->name), 0, 1));
    }
}
