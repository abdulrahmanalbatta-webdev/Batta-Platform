<?php

namespace App\Models;

use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\WorkshopFormat;
use Database\Factories\WorkshopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Paid bookings: one completed workshop order is one seat.
     *
     * @return HasMany<Order, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Order::class, 'item_id')
            ->where('item_type', OrderItemType::Workshop)
            ->where('status', OrderStatus::Completed);
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
}
