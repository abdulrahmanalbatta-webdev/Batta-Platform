<?php

namespace App\Support;

use App\Enums\ArticleStatus;
use App\Enums\CourseStatus;
use App\Enums\LeadStage;
use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Models\Article;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\LessonCompletion;
use App\Models\Order;
use App\Models\Student;
use App\Models\Workshop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The numbers on the dashboard home page. Revenue is what customers paid for completed orders (refunded ones
 * drop out) by the day they paid, plus the budget of project requests won, by the day they were won.
 *
 * The aggregates are cached for a few minutes; the lists at the bottom are always fresh.
 */
class DashboardSummary
{
    public const CACHE_KEY = 'dashboard.summary';

    public const CACHE_SECONDS = 300;

    public function __construct(private CourseProgress $progress) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return [
            ...Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => $this->aggregates()),
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

        $orders = Order::query()->where('status', OrderStatus::Completed)->where('paid_at', '>=', $first)->get(['item_type', 'total', 'paid_at']);
        $won = Lead::query()->where('stage', LeadStage::Won)->where('decided_at', '>=', $first)->get(['budget', 'decided_at']);
        $students = Student::query()->where('created_at', '>=', $first)->pluck('created_at');
        $lessons = LessonCompletion::query()->where('completed_at', '>=', $first)->pluck('completed_at');

        $perMonth = fn (Collection $dates, ?Collection $amounts = null): array => $months
            ->map(fn (Carbon $month): float => (float) $dates
                ->keys()
                ->filter(fn (int $i): bool => $dates[$i]->isSameMonth($month))
                ->sum(fn (int $i): float => $amounts === null ? 1 : (float) $amounts[$i]))
            ->all();

        $courseRevenue = $perMonth($orders->pluck('paid_at'), $orders->pluck('total'));
        $serviceRevenue = $perMonth($won->pluck('decided_at'), $won->pluck('budget'));

        // this month so far against the same stretch of last month
        [$from, $previousFrom, $previousTo] = [now()->startOfMonth(), now()->startOfMonth()->subMonthNoOverflow(), now()->subMonthNoOverflow()];
        $inMonth = fn (Collection $dates, ?Collection $amounts = null): array => [
            $this->sumBetween($dates, $amounts, $from, now()),
            $this->sumBetween($dates, $amounts, $previousFrom, $previousTo),
        ];

        $revenueDates = $orders->pluck('paid_at')->concat($won->pluck('decided_at'))->values();
        $revenueAmounts = $orders->pluck('total')->concat($won->pluck('budget'))->values();

        return [
            'month' => now()->locale('ar')->translatedFormat('F'),
            'previous_month' => now()->subMonthNoOverflow()->locale('ar')->translatedFormat('F'),
            'kpis' => [
                'revenue' => $this->kpi($inMonth($revenueDates, $revenueAmounts), array_map(fn (float $a, float $b): float => $a + $b, $courseRevenue, $serviceRevenue)),
                'students' => $this->kpi($inMonth($students), $perMonth($students)),
                'orders' => $this->kpi($inMonth($orders->pluck('paid_at')), $perMonth($orders->pluck('paid_at'))),
                'lessons' => $this->kpi($inMonth($lessons), $perMonth($lessons)),
                'completion' => $this->completionRate(),
            ],
            'revenue' => [
                'labels' => $months->map(fn (Carbon $month): string => $month->locale('ar')->translatedFormat('F'))->all(),
                'courses' => $courseRevenue,
                'services' => $serviceRevenue,
            ],
            'sales_mix' => $this->salesMix(),
            'top_courses' => Course::query()
                ->withSum('sales', 'total')
                ->orderByDesc('sales_sum_total')
                ->limit(4)
                ->get()
                ->filter(fn (Course $course): bool => (float) $course->sales_sum_total > 0)
                ->map(fn (Course $course): array => ['id' => $course->id, 'title' => $course->title, 'glyph' => $course->glyph(), 'revenue' => (float) $course->sales_sum_total])
                ->values()
                ->all(),
            'totals' => [
                'students' => Student::query()->count(),
                'published_courses' => Course::query()->where('status', CourseStatus::Published)->count(),
                'published_articles' => Article::query()->where('status', ArticleStatus::Published)->count(),
                'course_revenue' => round((float) Order::query()->where('status', OrderStatus::Completed)->sum('total'), 2),
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
     * @param  Collection<int, Carbon>  $dates
     * @param  Collection<int, mixed>|null  $amounts  null to count
     */
    private function sumBetween(Collection $dates, ?Collection $amounts, Carbon $from, Carbon $to): float
    {
        return (float) $dates->keys()
            ->filter(fn (int $i): bool => $dates[$i]->between($from, $to))
            ->sum(fn (int $i): float => $amounts === null ? 1 : (float) $amounts[$i]);
    }

    /**
     * Average progress over every enrolment, in percent.
     */
    private function completionRate(): int
    {
        $perStudent = $this->progress->forStudents(Enrollment::query()->distinct()->pluck('student_id'));
        $all = array_merge(...array_values(array_map('array_values', $perStudent)));

        return $all === [] ? 0 : (int) round(array_sum($all) / count($all));
    }

    /**
     * What the last 30 days' revenue came from.
     *
     * @return list<array{key: string, label: string, value: float}>
     */
    private function salesMix(): array
    {
        $since = now()->subDays(30);
        $byType = Order::query()
            ->where('status', OrderStatus::Completed)
            ->where('paid_at', '>=', $since)
            ->get(['item_type', 'total'])
            ->groupBy(fn (Order $order): string => $order->item_type->value)
            ->map(fn (Collection $orders): float => round((float) $orders->sum('total'), 2));

        return [
            ['key' => 'courses', 'label' => 'الدورات', 'value' => $byType[OrderItemType::Course->value] ?? 0.0],
            ['key' => 'workshops', 'label' => 'الورش', 'value' => $byType[OrderItemType::Workshop->value] ?? 0.0],
            ['key' => 'pro', 'label' => 'اشتراك Pro', 'value' => $byType[OrderItemType::ProMonth->value] ?? 0.0],
            ['key' => 'services', 'label' => 'خدمات التطوير', 'value' => (float) Lead::query()->where('stage', LeadStage::Won)->where('decided_at', '>=', $since)->sum('budget')],
        ];
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
