<?php

namespace App\Support;

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\WorkshopRegistration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The analytics page: registrations in courses and workshops and new students over a period, compared with the
 * period before it. Up to 90 days are shown day by day; a year month by month.
 */
class RegistrationsReport
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

        $registrations = $this->registrations($previousFrom);
        $current = $registrations->filter(fn (array $registration): bool => $registration['at']->gte($from));
        $previous = $registrations->filter(fn (array $registration): bool => $registration['at']->lt($previousTo));

        $students = Student::query()->where('created_at', '>=', $previousFrom)->get(['id', 'country', 'created_at']);
        $newStudents = $students->filter(fn (Student $student): bool => $student->created_at->gte($from));
        $previousStudents = $students->filter(fn (Student $student): bool => $student->created_at->lt($previousTo));

        $buckets = $this->buckets($from, $monthly);
        $bucketOf = fn (Carbon $date): string => $date->format($monthly ? 'Y-m' : 'Y-m-d');
        $courses = $current->where('type', 'course');
        $workshops = $current->where('type', 'workshop');

        return [
            'days' => $days,
            'kpis' => [
                'registrations' => $this->kpi($current->count(), $previous->count()),
                'enrollments' => $this->kpi($courses->count(), $previous->where('type', 'course')->count()),
                'workshop_registrations' => $this->kpi($workshops->count(), $previous->where('type', 'workshop')->count()),
                'students' => $this->kpi($newStudents->count(), $previousStudents->count()),
            ],
            'series' => [
                'labels' => $buckets->map(fn (Carbon $bucket): string => $bucket->locale('ar')->translatedFormat($monthly ? 'F' : 'j F'))->values()->all(),
                'registrations' => $buckets->keys()->map(fn (string $key): int => $current->filter(fn (array $registration): bool => $bucketOf($registration['at']) === $key)->count())->all(),
                'students' => $buckets->keys()->map(fn (string $key): int => $newStudents->filter(fn (Student $student): bool => $bucketOf($student->created_at) === $key)->count())->all(),
            ],
            'funnel' => $this->funnel($newStudents->modelKeys(), $current),
            'split' => [
                ['label' => 'الدورات', 'value' => $courses->count()],
                ['label' => 'الورش', 'value' => $workshops->count()],
            ],
            'top_items' => $current->groupBy(fn (array $registration): string => $registration['type'].'|'.$registration['name'])
                ->map(fn (Collection $group): array => [
                    'name' => $group->first()['name'],
                    'type_label' => $group->first()['type'] === 'course' ? 'دورة' : 'ورشة',
                    'registrations' => $group->count(),
                ])
                ->sortByDesc('registrations')->take(6)->values()->all(),
            'countries' => $this->countries($current->pluck('student_id')->unique()),
        ];
    }

    /**
     * Course enrollments and workshop seats taken since $from, oldest first.
     *
     * @return Collection<int, array{type: string, name: string, student_id: int, at: Carbon}>
     */
    private function registrations(Carbon $from): Collection
    {
        $enrollments = Enrollment::query()->with('course:id,title')->where('created_at', '>=', $from)->get(['course_id', 'student_id', 'created_at'])
            ->map(fn (Enrollment $enrollment): array => ['type' => 'course', 'name' => (string) $enrollment->course?->title, 'student_id' => $enrollment->student_id, 'at' => $enrollment->created_at]);
        $seats = WorkshopRegistration::query()->with('workshop:id,title')->where('created_at', '>=', $from)->get(['workshop_id', 'student_id', 'created_at'])
            ->map(fn (WorkshopRegistration $seat): array => ['type' => 'workshop', 'name' => (string) $seat->workshop?->title, 'student_id' => $seat->student_id, 'at' => $seat->created_at]);

        return $enrollments->concat($seats)->sortBy('at')->values();
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
     * @param  Collection<int, array{type: string, name: string, student_id: int, at: Carbon}>  $registrations
     * @return list<array{label: string, value: int}>
     */
    private function funnel(array $studentIds, Collection $registrations): array
    {
        $perStudent = $registrations->whereIn('student_id', $studentIds)->countBy('student_id');

        return [
            ['label' => 'حسابات جديدة', 'value' => count($studentIds)],
            ['label' => 'سجّلوا في دورة أو ورشة', 'value' => $perStudent->count()],
            ['label' => 'سجّلوا في أكثر من واحدة', 'value' => $perStudent->filter(fn (int $count): bool => $count > 1)->count()],
        ];
    }

    /**
     * Where the students who registered in the period live, as shares of them (the 6 biggest, then the rest).
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
