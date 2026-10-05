<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The analytics page: sales and students over a period, compared with the period before it.
 * Up to 90 days are shown day by day; a year month by month.
 */
class SalesReport
{
    public const PERIODS = [7, 30, 90, 365];

    public const CACHE_SECONDS = 300;

    /**
     * @return array<string, mixed>
     */
    public function build(int $days): array
    {
        return Cache::remember("analytics.{$days}", self::CACHE_SECONDS, fn (): array => $this->report($days));
    }

    /**
     * @return array<string, mixed>
     */
    private function report(int $days): array
    {
        $monthly = $days > 90;
        $from = $monthly ? now()->startOfMonth()->subMonths(11) : today()->subDays($days - 1);
        // the period before covers the same stretch: for the year, the same months up to the same day a year ago
        $previousFrom = $monthly ? $from->copy()->subYear() : $from->copy()->subDays($days);
        $previousTo = $monthly ? now()->subYear() : $from;

        $orders = Order::query()
            ->where('status', OrderStatus::Completed)
            ->where('paid_at', '>=', $previousFrom)
            ->get(['student_id', 'item_type', 'item_name', 'total', 'payment_method', 'paid_at']);
        $current = $orders->filter(fn (Order $order): bool => $order->paid_at->gte($from));
        $previous = $orders->filter(fn (Order $order): bool => $order->paid_at->lt($previousTo));

        $students = Student::query()->where('created_at', '>=', $previousFrom)->get(['id', 'country', 'created_at']);
        $newStudents = $students->filter(fn (Student $student): bool => $student->created_at->gte($from));
        $previousStudents = $students->filter(fn (Student $student): bool => $student->created_at->lt($previousTo));

        $buckets = $this->buckets($from, $monthly);
        $bucketOf = fn (Carbon $date): string => $date->format($monthly ? 'Y-m' : 'Y-m-d');

        return [
            'days' => $days,
            'kpis' => [
                'revenue' => $this->kpi((float) $current->sum('total'), (float) $previous->sum('total')),
                'orders' => $this->kpi($current->count(), $previous->count()),
                'students' => $this->kpi($newStudents->count(), $previousStudents->count()),
                'average_order' => $this->kpi(
                    $current->isEmpty() ? 0 : (float) $current->avg('total'),
                    $previous->isEmpty() ? 0 : (float) $previous->avg('total'),
                ),
            ],
            'series' => [
                'labels' => $buckets->map(fn (Carbon $bucket): string => $bucket->locale('ar')->translatedFormat($monthly ? 'F' : 'j F'))->values()->all(),
                'revenue' => $buckets->keys()->map(fn (string $key): float => round((float) $current->filter(fn (Order $order): bool => $bucketOf($order->paid_at) === $key)->sum('total'), 2))->all(),
                'students' => $buckets->keys()->map(fn (string $key): int => $newStudents->filter(fn (Student $student): bool => $bucketOf($student->created_at) === $key)->count())->all(),
            ],
            'funnel' => $this->funnel($newStudents->modelKeys(), $from),
            'payment_methods' => $current->countBy(fn (Order $order): string => $order->payment_method->label())->sortDesc()
                ->map(fn (int $count, string $label): array => ['label' => $label, 'value' => $count])->values()->all(),
            'top_products' => $current->groupBy(fn (Order $order): string => $order->item_type->value.'|'.$order->item_name)
                ->map(fn (Collection $orders): array => [
                    'name' => $orders->first()->item_name,
                    'type_label' => $orders->first()->item_type->label(),
                    'orders' => $orders->count(),
                    'revenue' => round((float) $orders->sum('total'), 2),
                ])
                ->sortByDesc('revenue')->take(6)->values()->all(),
            'countries' => $this->countries($current->pluck('student_id')->unique()),
        ];
    }

    /**
     * One bucket per day (or month) from $from until today, keyed "Y-m-d" (or "Y-m").
     *
     * @return Collection<string, Carbon>
     */
    private function buckets(Carbon $from, bool $monthly): Collection
    {
        $buckets = collect();

        for ($bucket = $from->copy(); $bucket->lte(now()); $monthly ? $bucket->addMonth() : $bucket->addDay()) {
            $buckets[$bucket->format($monthly ? 'Y-m' : 'Y-m-d')] = $bucket->copy();
        }

        return $buckets;
    }

    /**
     * @return array{value: float, previous: float, change: ?int}
     */
    private function kpi(float $value, float $previous): array
    {
        return [
            'value' => round($value, 2),
            'previous' => round($previous, 2),
            'change' => $previous > 0 ? (int) round(($value - $previous) / $previous * 100) : null,
        ];
    }

    /**
     * What the students who joined in the period went on to do.
     *
     * @param  array<int, int>  $studentIds
     * @return list<array{label: string, value: int}>
     */
    private function funnel(array $studentIds, Carbon $from): array
    {
        $orders = Order::query()->whereIn('student_id', $studentIds)->where('created_at', '>=', $from)->get(['student_id', 'status']);
        $paid = $orders->where('status', OrderStatus::Completed)->countBy('student_id');

        return [
            ['label' => 'حسابات جديدة', 'value' => count($studentIds)],
            ['label' => 'بدأوا طلب شراء', 'value' => $orders->unique('student_id')->count()],
            ['label' => 'أتمّوا الشراء', 'value' => $paid->count()],
            ['label' => 'اشتروا أكثر من مرة', 'value' => $paid->filter(fn (int $count): bool => $count > 1)->count()],
        ];
    }

    /**
     * Where the paying students of the period live, as shares of them (the 6 biggest, then the rest).
     *
     * @param  Collection<int, int>  $studentIds
     * @return list<array{label: string, value: int}>
     */
    private function countries(Collection $studentIds): array
    {
        $byCountry = Student::query()->whereKey($studentIds)->pluck('country')
            ->countBy(fn (?string $country): string => $country ?: 'غير محدد')
            ->sortDesc();
        $total = $byCountry->sum();

        if ($total === 0) {
            return [];
        }

        $shares = $byCountry->take(6)->map(fn (int $count, string $country): array => ['label' => $country, 'value' => (int) round($count / $total * 100)])->values();
        $rest = $byCountry->skip(6)->sum();

        return $rest > 0 ? [...$shares, ['label' => 'أخرى', 'value' => (int) round($rest / $total * 100)]] : $shares->all();
    }
}
