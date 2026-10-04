<?php

namespace Database\Seeders;

use App\Actions\Orders\CompleteOrder;
use App\Actions\Orders\FailOrder;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\RefundOrder;
use App\Enums\CouponScope;
use App\Enums\CourseStatus;
use App\Enums\DiscountType;
use App\Enums\OrderItemType;
use App\Enums\PaymentMethod;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonCompletion;
use App\Models\Student;
use App\Models\Workshop;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

/**
 * Sample students, coupons and a year of orders for local development (mt_rand is seeded, so every run gives the same data). Orders go through the same actions as
 * real ones, so enrolments, workshop seats and Pro months follow from them. Runs after ContentSeeder.
 */
class SalesSeeder extends Seeder
{
    private const FIRST_NAMES = ['محمد', 'أحمد', 'سارة', 'ليان', 'يوسف', 'رامي', 'نور', 'خالد', 'مريم', 'عمر', 'هبة', 'زيد', 'دانة', 'علي', 'جود', 'سلمى', 'آدم', 'رنا', 'مالك', 'تالا'];

    private const LATIN = ['mohammad', 'ahmad', 'sara', 'layan', 'yousef', 'rami', 'noor', 'khaled', 'maryam', 'omar', 'heba', 'zaid', 'dana', 'ali', 'jood', 'salma', 'adam', 'rana', 'malek', 'tala'];

    private const LAST_NAMES = ['الخطيب', 'النجار', 'العلي', 'حمدان', 'الشريف', 'عودة', 'منصور', 'سالم', 'الحسن', 'يونس', 'البرغوثي', 'قاسم'];

    private const COUNTRIES = ['السعودية', 'الأردن', 'فلسطين', 'مصر', 'الإمارات', 'الكويت', 'المغرب', 'قطر'];

    public function run(PlaceOrder $place, CompleteOrder $complete, RefundOrder $refund, FailOrder $fail): void
    {
        mt_srand(7);

        $students = collect(range(0, 47))->map(function (int $i): Student {
            $first = mt_rand(0, count(self::FIRST_NAMES) - 1);
            $student = Student::forceCreate([
                'name' => self::FIRST_NAMES[$first].' '.self::LAST_NAMES[mt_rand(0, count(self::LAST_NAMES) - 1)],
                'email' => self::LATIN[$first].mt_rand(10, 99).$i.'@mail.com',
                'country' => self::COUNTRIES[mt_rand(0, count(self::COUNTRIES) - 1)],
                'last_active_at' => now()->subDays(mt_rand(0, 100) > 75 ? mt_rand(31, 90) : mt_rand(0, 20)),
                'suspended_at' => mt_rand(0, 100) > 92 ? now()->subDays(mt_rand(1, 20)) : null,
                'created_at' => now()->subDays(mt_rand(10, 360)),
            ]);

            return $student;
        });

        $courses = Course::query()->where('status', CourseStatus::Published)->get();
        $workshops = Workshop::query()->where('date', '>=', today())->get();

        $this->coupons($courses->first());

        foreach (range(0, 63) as $i) {
            $student = $students[mt_rand(0, $students->count() - 1)];
            if ($student->isSuspended()) {
                continue;
            }

            $roll = mt_rand(0, 100);
            [$type, $item] = match (true) {
                $roll < 70 => [OrderItemType::Course, $courses[mt_rand(0, $courses->count() - 1)]],
                $roll < 85 && $workshops->isNotEmpty() => [OrderItemType::Workshop, $workshops[mt_rand(0, $workshops->count() - 1)]],
                default => [OrderItemType::ProMonth, null],
            };
            $coupon = mt_rand(0, 100) > 70 ? ['LAUNCH30', 'STUDENT10', 'NEXT20', 'WORKSHOP5'][mt_rand(0, 3)] : null;
            $method = [PaymentMethod::Card, PaymentMethod::Card, PaymentMethod::PayPal, PaymentMethod::ApplePay, PaymentMethod::BankTransfer][mt_rand(0, 4)];

            try {
                $order = $place->handle($student, $type, $item, $method, $coupon);
            } catch (ValidationException) {
                // already enrolled, or the coupon doesn't fit this item: try without the coupon once
                try {
                    $order = $place->handle($student, $type, $item, $method);
                } catch (ValidationException) {
                    continue;
                }
            }

            $outcome = mt_rand(0, 100);
            match (true) {
                $outcome < 6 => $fail->handle($order),
                $outcome < 12 => null,
                default => $complete->handle($order, round((float) $order->total * 0.05, 2)),
            };
            if ($outcome >= 92) {
                $refund->handle($order);
            }

            // spread the orders over the last year, more of them lately (a growing platform), never before the student joined
            $at = now()->subDays((int) round(330 * ($i / 63) ** 1.6))->subMinutes(mt_rand(0, 600))->max($student->created_at->copy()->addDay());
            $order->refresh()->forceFill([
                'created_at' => $at,
                'paid_at' => $order->paid_at ? $at->copy()->addMinutes(2) : null,
                'refunded_at' => $order->refunded_at ? $at->copy()->addDays(3) : null,
            ])->save();
        }

        $this->progress();
    }

    private function coupons(?Course $course): void
    {
        $coupons = [
            ['LAUNCH30', DiscountType::Percent, 30, CouponScope::AllCourses, 200, 28, true],
            ['NEXT20', DiscountType::Percent, 20, CouponScope::Course, 100, 43, true],
            ['STUDENT10', DiscountType::Fixed, 10, CouponScope::AllCourses, null, 89, true],
            ['WORKSHOP5', DiscountType::Fixed, 5, CouponScope::Workshops, 50, 25, true],
            ['SUMMER25', DiscountType::Percent, 25, CouponScope::AllCourses, 200, -33, true],
        ];

        foreach ($coupons as [$code, $type, $value, $scope, $limit, $days, $active]) {
            Coupon::create([
                'code' => $code, 'type' => $type, 'value' => $value, 'scope' => $scope,
                'course_id' => $scope === CouponScope::Course ? $course?->id : null,
                'usage_limit' => $limit, 'expires_on' => now()->addDays($days)->toDateString(), 'is_active' => $active,
            ]);
        }
    }

    /**
     * Mark a random share of each enrolled course's lessons as finished.
     */
    private function progress(): void
    {
        Enrollment::query()->with('course.lessons')->each(function (Enrollment $enrollment): void {
            $lessons = $enrollment->course->lessons;
            $done = $lessons->take((int) round($lessons->count() * mt_rand(0, 100) / 100));

            $done->each(fn ($lesson) => LessonCompletion::forceCreate([
                'student_id' => $enrollment->student_id,
                'lesson_id' => $lesson->id,
                'completed_at' => now()->subDays(mt_rand(0, 30)),
            ]));
        });
    }
}
