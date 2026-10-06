<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\ToolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['tool_category_id', 'name', 'short', 'color', 'why', 'since', 'url', 'is_affiliate', 'is_published'])]
class Tool extends Model
{
    /** @use HasFactory<ToolFactory> */
    use HasFactory, LogsActivity;

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

    protected static function booted(): void
    {
        // a deleted tool takes its logo with it
        static::deleted(function (Tool $tool): void {
            if ($tool->logo_path) {
                Storage::disk('public')->delete($tool->logo_path);
            }
        });
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null);
    }

    /**
     * @return BelongsTo<ToolCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ToolCategory::class, 'tool_category_id');
    }

    public function activityLabel(): string
    {
        return 'الأداة';
    }

    protected function activityStateAttribute(): ?string
    {
        return 'is_published';
    }

    protected function activityStateLabel(): ?string
    {
        return $this->is_published ? 'منشورة' : 'مخفية';
    }

    /**
     * What the public site may show.
     *
     * @param  Builder<Tool>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }
}
