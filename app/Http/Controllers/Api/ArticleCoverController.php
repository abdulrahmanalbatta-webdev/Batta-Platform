<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ArticleCoverController extends Controller
{
    /**
     * Replace the article's cover image.
     */
    public function store(Request $request, Article $article): ArticleResource
    {
        $request->validate([
            'cover' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $previous = $article->cover_path;
        $article->forceFill(['cover_path' => $request->file('cover')->store('articles', 'public')])->save();

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        return (new ArticleResource($article))->withContent();
    }
}
