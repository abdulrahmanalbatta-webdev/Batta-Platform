<?php

namespace App\Http\Resources;

use App\Models\Tool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tool
 */
class ToolResource extends JsonResource
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
            'name' => $this->name,
            'short' => $this->short,
            'color' => $this->color,
            'logo_url' => $this->logo_url,
            'category_id' => $this->tool_category_id,
            'why' => $this->why,
            'since' => $this->since,
            'url' => $this->url,
            'is_affiliate' => $this->is_affiliate,
            'is_published' => $this->is_published,
            'clicks' => $this->clicks,
        ];
    }
}
