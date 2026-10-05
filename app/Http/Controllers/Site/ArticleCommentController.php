<?php

namespace App\Http\Controllers\Site;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Site\ArticleCommentResource;
use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\Student;
use App\Support\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ArticleCommentController extends Controller
{
    /**
     * A published article's published comments, oldest first (a conversation reads top to bottom), 50 a page.
     */
    public function index(string $slug): AnonymousResourceCollection
    {
        $article = Article::query()->published()->where('slug', $slug)->firstOrFail();

        return ArticleCommentResource::collection(
            $article->comments()
                ->where('status', ReviewStatus::Published)
                ->with('student:id,name')
                ->oldest()->oldest('id')
                ->paginate(50),
        );
    }

    /**
     * A signed-in student comments on a published article. The comment waits for the team's moderation before it
     * shows; comments can be switched off in settings (الإعدادات ← التعليقات على المقالات).
     */
    public function store(Request $request, PlatformSettings $settings, string $slug): JsonResponse
    {
        abort_unless((bool) $settings->get('article_comments'), 403, 'التعليقات على المقالات مغلقة حالياً.');

        $article = Article::query()->published()->where('slug', $slug)->firstOrFail();

        $validated = $request->validate(['body' => ['required', 'string', 'min:3', 'max:2000']], [], ['body' => 'التعليق']);

        /** @var Student $student */
        $student = $request->user();

        ArticleComment::query()->create([
            'article_id' => $article->id,
            'student_id' => $student->id,
            'body' => $validated['body'],
        ]);

        return response()->json(['message' => 'وصل تعليقك، وسيظهر بعد مراجعته.'], 201);
    }
}
