<?php

namespace App\Models;

use App\Enums\MentorshipStatus;
use App\Models\Concerns\LogsActivity;
use Database\Factories\MentorshipRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A student's application to the paid mentorship. It goes: pending → accepted (waiting for the transfer) →
 * proof submitted → paid → scheduled → completed; or ends rejected, or expired when no receipt came in time.
 */
#[Fillable(['package_id', 'package_name', 'price', 'goal', 'background', 'links', 'availability', 'notes'])]
class MentorshipRequest extends Model
{
    /** @use HasFactory<MentorshipRequestFactory> */
    use HasFactory, LogsActivity;

    /**
     * Where the students are: session times in emails are written in this timezone.
     */
    public const TIMEZONE = 'Asia/Gaza';

    /**
     * Payment receipts are deleted this many months after a request is finished (routes/console.php).
     */
    public const KEEP_PROOF_MONTHS = 6;

    /**
     * Mirrors the column default, so a just-created request reads as pending before it's reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MentorshipStatus::class,
            'price' => 'integer',
            'accepted_at' => 'datetime',
            'payment_due_at' => 'datetime',
            'proof_uploaded_at' => 'datetime',
            'paid_at' => 'datetime',
            'session_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(fn (MentorshipRequest $request) => $request->deleteProof());
    }

    /**
     * Payment receipts live on the private default disk, read only through the dashboard.
     */
    public static function proofDisk(): string
    {
        return config('filesystems.default');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function number(): string
    {
        return 'M-'.$this->id;
    }

    /**
     * Still waiting for the transfer, and the window to send the receipt hasn't closed.
     */
    public function awaitsPayment(): bool
    {
        return $this->status === MentorshipStatus::Accepted && $this->payment_due_at?->isFuture();
    }

    public function deleteProof(): void
    {
        if ($this->proof_path !== null) {
            Storage::disk(self::proofDisk())->delete($this->proof_path);
        }
    }

    public function activityLabel(): string
    {
        return 'طلب الإرشاد';
    }

    public function activityName(): string
    {
        return $this->number().' · '.($this->student?->name ?? '');
    }

    protected function activityStateAttribute(): ?string
    {
        return 'status';
    }

    protected function activityStateLabel(): ?string
    {
        return $this->status->label();
    }
}
