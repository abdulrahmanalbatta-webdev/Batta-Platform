<?php

namespace App\Http\Controllers\Site;

use App\Enums\LeadStage;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Course;
use App\Models\Lead;
use App\Models\Review;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class StatsController extends Controller
{
    /**
     * Seconds the numbers are kept before they're counted again.
     */
    public const CACHE_SECONDS = 600;

    /**
     * The real numbers the site shows in its stats strips: students, published courses, delivered projects
     * (won leads), published articles, and the average of the published reviews.
     */
    public function __invoke(): JsonResponse
    {
        $stats = Cache::remember('site.stats', self::CACHE_SECONDS, fn (): array => [
            'students' => Student::query()->count(),
            'courses' => Course::query()->published()->count(),
            'projects' => Lead::query()->where('stage', LeadStage::Won)->count(),
            'articles' => Article::query()->published()->count(),
            'reviews' => Review::query()->where('status', ReviewStatus::Published)->count(),
            'rating' => round((float) Review::query()->where('status', ReviewStatus::Published)->avg('rating'), 1),
        ]);

        return response()->json(['data' => $stats]);
    }
}
