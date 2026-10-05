<?php

namespace App\Http\Controllers\Site;

use App\Enums\ArticleCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\Site\ArticleResource;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ArticleController extends Controller
{
    /**
     * Published articles, newest first, 12 a page (?per_page= up to 50); ?category= narrows them, ?featured=1 keeps the featured ones.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'category' => ['nullable', Rule::enum(ArticleCategory::class)],
            'featured' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ]);

        return ArticleResource::collection(
            Article::query()
                ->published()
                ->with('author:id,name')
                ->when($validated['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
                ->when($request->boolean('featured'), fn ($query) => $query->where('is_featured', true))
                ->latest('published_at')->latest('id')
                ->paginate((int) ($validated['per_page'] ?? 12)),
        );
    }

    /**
     * A published article; each read counts one view (without touching the article's "last edited").
     */
    public function show(string $slug): ArticleResource
    {
        $article = Article::query()->published()->with('author:id,name')->where('slug', $slug)->firstOrFail();

        DB::table('articles')->where('id', $article->id)->increment('views');

        return (new ArticleResource($article))->withContent();
    }
}
