<?php

namespace Database\Seeders;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Illuminate\Database\Seeder;

/**
 * Sample students and a year of registrations in courses and workshops for local development (mt_rand is seeded,
 * so every run gives the same data). Runs after ContentSeeder.
 */
class StudentSeeder extends Seeder
{
    private const FIRST_NAMES = ['محمد', 'أحمد', 'سارة', 'ليان', 'يوسف', 'رامي', 'نور', 'خالد', 'مريم', 'عمر', 'هبة', 'زيد', 'دانة', 'علي', 'جود', 'سلمى', 'آدم', 'رنا', 'مالك', 'تالا'];

    private const LATIN = ['mohammad', 'ahmad', 'sara', 'layan', 'yousef', 'rami', 'noor', 'khaled', 'maryam', 'omar', 'heba', 'zaid', 'dana', 'ali', 'jood', 'salma', 'adam', 'rana', 'malek', 'tala'];

    private const LAST_NAMES = ['الخطيب', 'النجار', 'العلي', 'حمدان', 'الشريف', 'عودة', 'منصور', 'سالم', 'الحسن', 'يونس', 'البرغوثي', 'قاسم'];

    private const COUNTRIES = ['السعودية', 'الأردن', 'فلسطين', 'مصر', 'الإمارات', 'الكويت', 'المغرب', 'قطر'];

    public function run(): void
    {
        mt_srand(7);

        $students = collect(range(0, 47))->map(function (int $i): Student {
            $first = mt_rand(0, count(self::FIRST_NAMES) - 1);

            return Student::forceCreate([
                'name' => self::FIRST_NAMES[$first].' '.self::LAST_NAMES[mt_rand(0, count(self::LAST_NAMES) - 1)],
                'email' => self::LATIN[$first].mt_rand(10, 99).$i.'@mail.com',
                'country' => self::COUNTRIES[mt_rand(0, count(self::COUNTRIES) - 1)],
                'last_active_at' => now()->subDays(mt_rand(0, 100) > 75 ? mt_rand(31, 90) : mt_rand(0, 20)),
                'suspended_at' => mt_rand(0, 100) > 92 ? now()->subDays(mt_rand(1, 20)) : null,
                'created_at' => now()->subDays(mt_rand(10, 360)),
            ]);
        });

        $courses = Course::query()->where('status', CourseStatus::Published)->get();
        $workshops = Workshop::query()->where('date', '>=', today())->get();

        foreach (range(0, 79) as $i) {
            $student = $students[mt_rand(0, $students->count() - 1)];
            if ($student->isSuspended()) {
                continue;
            }

            // spread the registrations over the last year, more of them lately (a growing platform), never before the student joined
            $at = now()->subDays((int) round(330 * ($i / 79) ** 1.6))->subMinutes(mt_rand(0, 600))->max($student->created_at->copy()->addDay());

            if (mt_rand(0, 100) < 80 || $workshops->isEmpty()) {
                $course = $courses[mt_rand(0, $courses->count() - 1)];
                if (! $student->enrollments()->where('course_id', $course->id)->exists()) {
                    Enrollment::forceCreate(['student_id' => $student->id, 'course_id' => $course->id, 'created_at' => $at, 'updated_at' => $at]);
                }

                continue;
            }

            // workshops are upcoming, so their seats were taken in the last few weeks
            $workshop = $workshops[mt_rand(0, $workshops->count() - 1)];
            if ($workshop->seatsTaken() < $workshop->seats && ! $workshop->registrations()->where('student_id', $student->id)->exists()) {
                $at = now()->subDays(mt_rand(0, 20))->max($student->created_at->copy()->addDay());
                WorkshopRegistration::forceCreate(['student_id' => $student->id, 'workshop_id' => $workshop->id, 'created_at' => $at, 'updated_at' => $at]);
            }
        }
    }
}
