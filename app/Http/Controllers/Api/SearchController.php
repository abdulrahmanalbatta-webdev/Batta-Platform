<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\Lead;
use App\Models\LearningPath;
use App\Models\Student;
use App\Models\Tool;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const PER_GROUP = 5;

    /**
     * The topbar search: up to 5 matches per kind of record, each with the dashboard page that shows it
     * (page is a key of the routes in layouts/partials/app-config, opened with App.url(page, params)).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $like = '%'.addcslashes(trim($validated['q']), '\\%_').'%';

        $groups = [
            ['students', 'الطلاب', Student::query()->where(fn (Builder $query) => $query->whereLike('name', $like)->orWhereLike('email', $like))
                ->limit(self::PER_GROUP)->get(['id', 'name', 'email'])
                ->map(fn (Student $student): array => $this->item($student->name, $student->email, 'students', ['q' => $student->email]))],
            ['courses', 'الدورات', Course::query()->whereLike('title', $like)->limit(self::PER_GROUP)->get(['id', 'title', 'status'])
                ->map(fn (Course $course): array => $this->item($course->title, $course->status->label(), 'course-edit', ['id' => $course->id]))],
            ['paths', 'المسارات', LearningPath::query()->whereLike('title', $like)->limit(self::PER_GROUP)->get(['id', 'title', 'is_published'])
                ->map(fn (LearningPath $path): array => $this->item($path->title, $path->is_published ? 'منشور' : 'مخفي', 'path-edit', ['id' => $path->id]))],
            ['articles', 'المقالات', Article::query()->whereLike('title', $like)->limit(self::PER_GROUP)->get(['id', 'title', 'status'])
                ->map(fn (Article $article): array => $this->item($article->title, $article->status->label(), 'article-edit', ['id' => $article->id]))],
            ['workshops', 'الورش', Workshop::query()->whereLike('title', $like)->limit(self::PER_GROUP)->get(['id', 'title', 'date'])
                ->map(fn (Workshop $workshop): array => $this->item($workshop->title, $workshop->date->toDateString(), 'workshops'))],
            ['tools', 'الأدوات', Tool::query()->whereLike('name', $like)->limit(self::PER_GROUP)->get(['id', 'name', 'why'])
                ->map(fn (Tool $tool): array => $this->item($tool->name, $tool->why, 'tools', ['q' => $tool->name]))],
            ['leads', 'طلبات المشاريع', Lead::query()->where(fn (Builder $query) => $query->whereLike('name', $like)->orWhereLike('company', $like)->orWhereLike('email', $like))
                ->limit(self::PER_GROUP)->get(['id', 'name', 'company', 'stage'])
                ->map(fn (Lead $lead): array => $this->item($lead->company ?: $lead->name, $lead->name.' · '.$lead->stage->label(), 'leads', ['lead' => $lead->id]))],
            ['messages', 'الرسائل', Conversation::query()->where(fn (Builder $query) => $query->whereLike('name', $like)->orWhereLike('email', $like))
                ->orderByDesc('last_message_at')->limit(self::PER_GROUP)->get(['id', 'name', 'email'])
                ->map(fn (Conversation $conversation): array => $this->item($conversation->name, $conversation->email, 'messages', ['c' => $conversation->id]))],
        ];

        return response()->json([
            'data' => collect($groups)
                ->map(fn (array $group): array => ['group' => $group[0], 'label' => $group[1], 'items' => $group[2]->values()])
                ->filter(fn (array $group): bool => $group['items']->isNotEmpty())
                ->values(),
        ]);
    }

    /**
     * @param  array<string, int|string>  $params
     * @return array{title: string, subtitle: string, page: string, params: array<string, int|string>}
     */
    private function item(string $title, string $subtitle, string $page, array $params = []): array
    {
        return ['title' => $title, 'subtitle' => $subtitle, 'page' => $page, 'params' => $params];
    }
}
