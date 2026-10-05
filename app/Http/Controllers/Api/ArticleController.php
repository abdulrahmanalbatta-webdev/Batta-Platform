<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Support\Slug;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ArticleController extends Controller
{
    /**
     * Every article, newest first, without bodies.
     */
    public function index(): AnonymousResourceCollection
    {
        return ArticleResource::collection(
            Article::query()
                ->withCount(['comments as published_comments_count' => fn ($query) => $query->where('status', ReviewStatus::Published)])
                ->latest('updated_at')->latest('id')->get(),
        );
    }

    public function show(Article $article): ArticleResource
    {
        return (new ArticleResource($article))->withContent();
    }

    /**
     * Create an article; its slug comes from the title and stays fixed afterwards, so links keep working.
     */
    public function store(ArticleRequest $request): ArticleResource
    {
        $article = new Article($request->articleAttributes());
        $article->slug = Slug::unique(Article::class, $article->title, 'article');
        $article->author()->associate($request->user());
        $article->save();

        return (new ArticleResource($article))->withContent();
    }

    public function update(ArticleRequest $request, Article $article): ArticleResource
    {
        $article->update($request->articleAttributes());

        return (new ArticleResource($article))->withContent();
    }

    public function destroy(Article $article): Response
    {
        if ($article->cover_path) {
            Storage::disk('public')->delete($article->cover_path);
        }

        $article->delete();

        return response()->noContent();
    }
}
