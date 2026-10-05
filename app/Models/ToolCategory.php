<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\ToolCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'color'])]
class ToolCategory extends Model
{
    /** @use HasFactory<ToolCategoryFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return HasMany<Tool, $this>
     */
    public function tools(): HasMany
    {
        return $this->hasMany(Tool::class);
    }

    public function activityLabel(): string
    {
        return 'تصنيف الأدوات';
    }
}
