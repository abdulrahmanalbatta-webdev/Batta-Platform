<?php

namespace App\Http\Resources\Site;

use App\Models\Tool;
use App\Models\ToolCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A tools category with its published tools (expects the tools relation loaded and filtered).
 *
 * @mixin ToolCategory
 */
class ToolCategoryResource extends JsonResource
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
            'color' => $this->color,
            'tools' => $this->tools->map(fn (Tool $tool): array => [
                'id' => $tool->id,
                'name' => $tool->name,
                'short' => $tool->short,
                'color' => $tool->color,
                'logo_url' => $tool->logo_url,
                'why' => $tool->why,
                'since' => $tool->since,
                'url' => $tool->url,
                'is_affiliate' => $tool->is_affiliate,
            ])->all(),
        ];
    }
}
