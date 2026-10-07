<?php

namespace App\Http\Resources\Site;

use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An upcoming workshop with the seats still free (the bookings themselves stay private).
 *
 * @mixin Workshop
 */
class WorkshopResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'date' => $this->date->toDateString(),
            'time' => substr($this->start_time, 0, 5),
            'format' => $this->format->value,
            'format_label' => $this->format->label(),
            'place' => $this->place,
            'seats' => $this->seats,
            'seats_left' => max(0, $this->seats - $this->seatsTaken()),
            'is_full' => $this->state() === 'full',
        ];
    }
}
