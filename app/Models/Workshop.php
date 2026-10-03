<?php

namespace App\Models;

use App\Enums\WorkshopFormat;
use Database\Factories\WorkshopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'description', 'date', 'start_time', 'format', 'place', 'price', 'seats'])]
class Workshop extends Model
{
    /** @use HasFactory<WorkshopFactory> */
    use HasFactory;

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
            'price' => 'decimal:2',
        ];
    }

    /**
     * Seats booked so far. Registrations arrive with orders (phase 3); until then nobody is booked.
     */
    public function seatsTaken(): int
    {
        return 0;
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
}
