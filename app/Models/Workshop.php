<?php

namespace App\Models;

use App\Enums\WorkshopFormat;
use App\Models\Concerns\HasComments;
use App\Models\Concerns\LogsActivity;
use Database\Factories\WorkshopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'description', 'date', 'start_time', 'format', 'place', 'seats'])]
class Workshop extends Model
{
    /** @use HasFactory<WorkshopFactory> */
    use HasComments, HasFactory, LogsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'format' => WorkshopFormat::class,
        ];
    }

    /**
     * The students' seats: one registration is one seat.
     *
     * @return HasMany<WorkshopRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(WorkshopRegistration::class);
    }

    /**
     * Seats booked so far (uses withCount('registrations') when loaded).
     */
    public function seatsTaken(): int
    {
        return (int) ($this->registrations_count ?? $this->registrations()->count());
    }

    public function hasEnded(): bool
    {
        return $this->date->isBefore(today());
    }

    /**
     * ended | full | open, derived from the date and the bookings rather than stored.
     */
    public function state(): string
    {
        return match (true) {
            $this->hasEnded() => 'ended',
            $this->seatsTaken() >= $this->seats => 'full',
            default => 'open',
        };
    }

    public function activityLabel(): string
    {
        return 'الورشة';
    }
}
