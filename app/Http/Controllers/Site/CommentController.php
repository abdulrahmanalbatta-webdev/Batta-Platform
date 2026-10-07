<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\Site\CommentResource;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Course;
use App\Models\Student;
use App\Models\Workshop;
use App\Support\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Comments under a published article (/articles/{slug}), a published course (/courses/{slug}) or a workshop
 * (/workshops/{id}). The route says which ({type}); students reply to a comment one level deep.
 */
class CommentController extends Controller
{
    /**
     * The published comments, oldest first (a conversation reads top to bottom), 50 a page, each with its
     * published replies.
     */
    public function index(string $key, string $type): AnonymousResourceCollection
    {
        return CommentResource::collection(
            $this->target($type, $key)->comments()
                ->published()
                ->whereNull('parent_id')
                ->with(['student:id,name', 'replies' => fn ($replies) => $replies->published()->with('student:id,name')->oldest()->oldest('id')])
                ->oldest()->oldest('id')
                ->paginate(50),
        );
    }

    /**
     * A signed-in student comments, or replies to a published comment (parent_id). It waits for the team's
     * moderation before it shows; comments can be switched off in settings (الإعدادات ← التعليقات).
     */
    public function store(Request $request, PlatformSettings $settings, string $key, string $type): JsonResponse
    {
        abort_unless((bool) $settings->get('article_comments'), 403, 'التعليقات مغلقة حالياً.');

        $target = $this->target($type, $key);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:2000'],
            // a reply answers a published comment on the same page, never another reply
            'parent_id' => ['nullable', 'integer', Rule::exists('comments', 'id')->where(fn ($query) => $query
                ->where('commentable_type', $target->getMorphClass())
                ->where('commentable_id', $target->getKey())
                ->whereNull('parent_id')
                ->where('status', 'published'))],
        ], [], ['body' => 'التعليق', 'parent_id' => 'التعليق الذي ترد عليه']);

        /** @var Student $student */
        $student = $request->user();

        $target->comments()->create([
            'parent_id' => $validated['parent_id'] ?? null,
            'student_id' => $student->id,
            'body' => $validated['body'],
        ]);

        return response()->json([
            'message' => isset($validated['parent_id']) ? 'وصل ردّك، وسيظهر بعد مراجعته.' : 'وصل تعليقك، وسيظهر بعد مراجعته.',
        ], 201);
    }

    /**
     * The article, course or workshop the comments belong to; drafts can't be read or commented on.
     */
    private function target(string $type, string $key): Article|Course|Workshop
    {
        /** @var Article|Course|Workshop $target */
        $target = match ($type) {
            'article' => Article::query()->published()->where('slug', $key)->firstOrFail(),
            'course' => Course::query()->published()->where('slug', $key)->firstOrFail(),
            'workshop' => Workshop::query()->findOrFail((int) $key),
        };

        return $target;
    }
}
