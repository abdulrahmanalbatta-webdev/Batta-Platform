<?php

namespace App\Models;

use Database\Factories\ToolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tool_category_id', 'name', 'short', 'color', 'why', 'since', 'url', 'is_affiliate', 'is_published'])]
class Tool extends Model
{
    /** @use HasFactory<ToolFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_affiliate' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ToolCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ToolCategory::class, 'tool_category_id');
    }
}
