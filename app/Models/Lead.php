<?php

namespace App\Models;

use App\Enums\LeadService;
use App\Enums\LeadStage;
use App\Models\Concerns\LogsActivity;
use App\Observers\LeadObserver;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(LeadObserver::class)]
#[Fillable(['name', 'company', 'email', 'phone', 'service', 'budget', 'stage', 'note'])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, LogsActivity;

    /**
     * Mirrors the column defaults, so a just-created request reads as new before it's reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'stage' => 'new',
        'budget' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service' => LeadService::class,
            'stage' => LeadStage::class,
            'budget' => 'integer',
        ];
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function number(): string
    {
        return 'L-'.$this->id;
    }

    public function activityLabel(): string
    {
        return 'طلب المشروع';
    }

    public function activityName(): string
    {
        return $this->company ?: $this->name;
    }

    protected function activityStateAttribute(): ?string
    {
        return 'stage';
    }

    protected function activityStateLabel(): ?string
    {
        return $this->stage->label();
    }
}
