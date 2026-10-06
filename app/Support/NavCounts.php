<?php

namespace App\Support;

use App\Enums\LeadStage;
use App\Enums\ReviewStatus;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Review;

/**
 * The badges on the sidebar links, rendered into every dashboard page (layouts/partials/app-config).
 */
class NavCounts
{
    /**
     * @return array{messages: int, reviews: int, comments: int, leads: int}
     */
    public static function all(): array
    {
        return [
            'messages' => Conversation::query()->whereNull('read_at')->count(),
            'reviews' => Review::query()->where('status', ReviewStatus::Pending)->count(),
            'comments' => Comment::query()->where('status', ReviewStatus::Pending)->count(),
            'leads' => Lead::query()->where('stage', LeadStage::New)->count(),
        ];
    }
}
