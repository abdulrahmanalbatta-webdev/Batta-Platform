<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\Site\LearningPathResource;
use App\Models\Article;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Workshop;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LearningPathController extends Controller
{
    /**
     * The published paths in the dashboard's order.
     */
    public function index(): AnonymousResourceCollection
    {
        return LearningPathResource::collection(LearningPath::query()->published()->ordered()->get());
    }

    /**
     * One path's map, with the published courses, upcoming workshops and published articles its stages link to.
     */
    public function show(string $slug): LearningPathResource
    {
        $path = LearningPath::query()->published()->where('slug', $slug)->firstOrFail();
        $ids = $path->itemIds();

        return (new LearningPathResource($path))->withLinked([
            'course' => Course::query()->published()->withCardNumbers()->whereKey($ids['course'] ?? [])->get()->keyBy('id'),
            'workshop' => Workshop::query()->whereDate('date', '>=', today())->withCount('registrations')->whereKey($ids['workshop'] ?? [])->get()->keyBy('id'),
            'article' => Article::query()->published()->with('author:id,name')->whereKey($ids['article'] ?? [])->get()->keyBy('id'),
        ]);
    }
}
