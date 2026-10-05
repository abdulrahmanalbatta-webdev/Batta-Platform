<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lead
 */
class LeadResource extends JsonResource
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
            'number' => $this->number(),
            'name' => $this->name,
            'company' => $this->company,
            'email' => $this->email,
            'phone' => $this->phone,
            'service' => $this->service->value,
            'service_label' => $this->service->label(),
            'budget' => $this->budget,
            'stage' => $this->stage->value,
            'stage_label' => $this->stage->label(),
            'note' => $this->note,
            'date' => $this->created_at->toDateString(),
        ];
    }
}
