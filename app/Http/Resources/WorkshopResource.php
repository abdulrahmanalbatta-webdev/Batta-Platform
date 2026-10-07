<?php

namespace App\Http\Resources;

use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Workshop
 */
class WorkshopResource extends JsonResource
{
    private const STATE_LABELS = ['open' => 'مفتوحة', 'full' => 'مكتملة', 'ended' => 'منتهية'];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = $this->state();

        return [
            'id' => $this->id,
            'code' => 'W-'.$this->id,
            'title' => $this->title,
            'description' => $this->description,
            'date' => $this->date->toDateString(),
            'time' => substr($this->start_time, 0, 5),
            'format' => $this->format->value,
            'format_label' => $this->format->label(),
            'place' => $this->place,
            'seats' => $this->seats,
            'taken' => $this->seatsTaken(),
            'state' => $state,
            'state_label' => self::STATE_LABELS[$state],
        ];
    }
}
