<?php

namespace App\Support;

use App\Enums\ArticleStatus;
use App\Enums\CourseStatus;
use App\Enums\LeadStage;
use App\Models\Article;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\Student;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The numbers on the dashboard home page: new students, registrations in courses and workshops, and project
 * requests, month by month.
 *
 * The aggregates are cached for a few minutes; the lists at the bottom are always fresh.
 */
class DashboardSummary
{
    public const CACHE_KEY = 'dashboard.summary';

    public const CACHE_SECONDS = 300;

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return [
            ...Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => $this->aggregates()),
            'recent_registrations' => $this->recentRegistrations(),
            'upcoming_workshops' => $this->upcomingWorkshops(),
            'pipeline' => $this->pipeline(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregates(): array
    {
        $months = collect(range(11, 0))->map(fn (int $back): Carbon => now()->startOfMonth()->subMonths($back));
        $first = $months->first();

        $students = Student::query()->where('created_at', '>=', $first)->pluck('created_at');
        $enrollments = Enrollment::query()->where('created_at', '>=', $first)->pluck('created_at');
        $seats = WorkshopRegistration::query()->where('created_at', '>=', $first)->pluck('created_at');
        $leads = Lead::query()->where('created_at', '>=', $first)->pluck('created_at');

        $perMonth = fn (Collection $dates): array => $months
            ->map(fn (Carbon $month): float => (float) $dates->filter(fn (Carbon $date): bool => $date->isSameMonth($month))->count())
            ->all();

        // this month so far against the same stretch of last month
        [$from, $previousFrom, $previousTo] = [now()->startOfMonth(), now()->startOfMonth()->subMonthNoOverflow(), now()->subMonthNoOverflow()];
        $inMonth = fn (Collection $dates): array => [
            (float) $dates->filter(fn (Carbon $date): bool => $date->between($from, now()))->count(),
            (float) $dates->filter(fn (Carbon $date): bool => $date->between($previousFrom, $previousTo))->count(),
        ];

        $since = now()->subDays(30);

        return [
            'month' => now()->locale('ar')->translatedFormat('F'),
            'previous_month' => now()->subMonthNoOverflow()->locale('ar')->translatedFormat('F'),
            'kpis' => [
                'students' => $this->kpi($inMonth($students), $perMonth($students)),
                'enrollments' => $this->kpi($inMonth($enrollments), $perMonth($enrollments)),
                'workshop_registrations' => $this->kpi($inMonth($seats), $perMonth($seats)),
                'leads' => $this->kpi($inMonth($leads), $perMonth($leads)),
            ],
            'registrations' => [
                'labels' => $months->map(fn (Carbon $month): string => $month->locale('ar')->translatedFormat('F'))->all(),
                'courses' => $perMonth($enrollments),
                'workshops' => $perMonth($seats),
            ],
            'registrations_mix' => [
                ['key' => 'courses', 'label' => 'الدورات', 'value' => $enrollments->filter(fn (Carbon $date): bool => $date->gte($since))->count()],
                ['key' => 'workshops', 'label' => 'الورش', 'value' => $seats->filter(fn (Carbon $date): bool => $date->gte($since))->count()],
            ],
            'top_courses' => Course::query()
                ->withCount('enrollments')
                ->orderByDesc('enrollments_count')
                ->limit(4)
                ->get()
                ->filter(fn (Course $course): bool => $course->enrollments_count > 0)
                ->map(fn (Course $course): array => ['id' => $course->id, 'title' => $course->title, 'glyph' => $course->glyph(), 'students' => $course->enrollments_count])
                ->values()
                ->all(),
            'totals' => [
                'students' => Student::query()->count(),
                'published_courses' => Course::query()->where('status', CourseStatus::Published)->count(),
                'published_articles' => Article::query()->where('status', ArticleStatus::Published)->count(),
                'enrollments' => Enrollment::query()->count(),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array{float, float}  $current  [this month so far, same stretch of last month]
     * @param  array<int, float>  $spark  the last 12 months
     * @return array{value: float, previous: float, change: ?int, spark: array<int, float>}
     */
    private function kpi(array $current, array $spark): array
    {
        [$value, $previous] = $current;

        return [
            'value' => round($value, 2),
            'previous' => round($previous, 2),
            // null when there's nothing to compare with
            'change' => $previous > 0 ? (int) round(($value - $previous) / $previous * 100) : null,
            'spark' => $spark,
        ];
    }

    /**
     * The latest registrations in courses and workshops, newest first.
     *
     * @return list<array{type: string, type_label: string, title: string, student: array{id: int, name: string, initial: string, email: string}, at: string}>
     */
    private function recentRegistrations(): array
    {
        $student = fn (Student $student): array => ['id' => $student->id, 'name' => $student->name, 'initial' => $student->initial, 'email' => $student->email];

        $enrollments = Enrollment::query()->with(['student', 'course:id,title'])->latest()->latest('id')->limit(6)->get()
            ->map(fn (Enrollment $enrollment): array => ['type' => 'course', 'type_label' => 'دورة', 'title' => (string) $enrollment->course?->title, 'student' => $student($enrollment->student), 'at' => $enrollment->created_at->toIso8601String()]);
        $seats = WorkshopRegistration::query()->with(['student', 'workshop:id,title'])->latest()->latest('id')->limit(6)->get()
            ->map(fn (WorkshopRegistration $seat): array => ['type' => 'workshop', 'type_label' => 'ورشة', 'title' => (string) $seat->workshop?->title, 'student' => $student($seat->student), 'at' => $seat->created_at->toIso8601String()]);

        return $enrollments->concat($seats)->sortByDesc('at')->take(6)->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function upcomingWorkshops(): array
    {
        return Workshop::query()
            ->withCount('registrations')
            ->whereDate('date', '>=', today())
            ->orderBy('date')
            ->limit(4)
            ->get()
            ->map(fn (Workshop $workshop): array => [
                'id' => $workshop->id,
                'title' => $workshop->title,
                'date' => $workshop->date->toDateString(),
                'format_label' => $workshop->format->label(),
                'taken' => $workshop->seatsTaken(),
                'seats' => $workshop->seats,
            ])
            ->all();
    }

    /**
     * @return array{value: int, stages: list<array{key: string, label: string, count: int}>}
     */
    private function pipeline(): array
    {
        $counts = Lead::query()->toBase()->selectRaw('stage, count(*) as total, sum(budget) as budget')->groupBy('stage')->get()->keyBy('stage');

        return [
            // open and won requests
            'value' => (int) collect(LeadStage::cases())
                ->reject(fn (LeadStage $stage): bool => $stage === LeadStage::Lost)
                ->sum(fn (LeadStage $stage): int => (int) ($counts[$stage->value]->budget ?? 0)),
            'stages' => collect(LeadStage::cases())
                ->map(fn (LeadStage $stage): array => ['key' => $stage->value, 'label' => $stage->label(), 'count' => (int) ($counts[$stage->value]->total ?? 0)])
                ->all(),
        ];
    }
}
