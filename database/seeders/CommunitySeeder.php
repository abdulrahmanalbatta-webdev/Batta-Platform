<?php

namespace Database\Seeders;

use App\Enums\LeadService;
use App\Enums\LeadStage;
use App\Enums\ReviewStatus;
use App\Models\Conversation;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Sample project requests, course reviews and conversations for local development. Runs after SalesSeeder:
 * reviews come from real enrolments and two conversations belong to students.
 */
class CommunitySeeder extends Seeder
{
    /**
     * @var list<array{string, string, string, LeadService, int, LeadStage, int, string}>
     */
    private const LEADS = [
        ['آدم عودة', 'شركة آدم', 'adam@adam.co', LeadService::Websites, 400, LeadStage::Lost, 26, 'اختار حلاً جاهزاً'],
        ['مريم سالم', 'أكاديمية مريم', 'maryam@academy.co', LeadService::WebApps, 5200, LeadStage::Won, 22, 'منصة دورات داخلية'],
        ['زيد النجار', 'متجر زيد', 'zaid@store.co', LeadService::Maintenance, 300, LeadStage::Won, 16, 'اشتراك صيانة شهري'],
        ['سلمى العلي', 'مطعم البيت', 'salma@albeit.co', LeadService::Websites, 600, LeadStage::Proposal, 12, 'قائمة طعام وحجز طاولات'],
        ['عمر قاسم', 'لوجستك برو', 'omar@logistic.pro', LeadService::Dashboards, 6500, LeadStage::Proposal, 10, 'لوحة تتبع شحنات'],
        ['نور الشريف', 'استوديو نور', 'noor@studio.co', LeadService::Websites, 800, LeadStage::Contacted, 7, 'موقع معرض أعمال'],
        ['خالد منصور', 'حاضنة رواد', 'khaled@rowad.org', LeadService::Training, 1200, LeadStage::Contacted, 5, 'ورشة يومين لفريق 18 شخصاً'],
        ['د. هبة يونس', 'عيادة الابتسامة', 'heba@smile.clinic', LeadService::WebApps, 4000, LeadStage::New, 3, 'نظام حجوزات وتذكير'],
        ['رامي حمدان', 'محمصة البن الذهبي', 'rami@goldenbean.co', LeadService::Stores, 2500, LeadStage::New, 2, 'متجر مع اشتراكات شهرية'],
    ];

    /**
     * @var list<array{int, string, ReviewStatus, ?string}>
     */
    private const REVIEWS = [
        [5, 'أفضل دورة أخذتها. المشروع النهائي صار أول شيء في ملف أعمالي.', ReviewStatus::Published, 'شكراً، فخور بمشروعك!'],
        [5, 'شرح واضح جداً ومناسب لمن يبدأ من الصفر.', ReviewStatus::Published, null],
        [4, 'محتوى قوي، أتمنى إضافة جزء عن الاختبارات بشكل أعمق.', ReviewStatus::Pending, null],
        [5, 'حصلت على أول عميل خلال الأسبوع السادس.', ReviewStatus::Published, null],
        [3, 'جيدة لكن قصيرة، كنت أتمنى أمثلة أكثر.', ReviewStatus::Pending, null],
        [1, 'اشترِ متابعين بأرخص الأسعار من موقعنا!!!', ReviewStatus::Hidden, null],
        [4, 'التمارين العملية ممتازة والدعم سريع.', ReviewStatus::Published, 'سعيد إنها فادتك، بالتوفيق!'],
        [5, 'الدورة غيّرت طريقة تفكيري في بناء المشاريع.', ReviewStatus::Published, null],
        [4, 'ممتازة، بس بعض الدروس بدها تحديث للإصدار الجديد.', ReviewStatus::Published, 'معك حق، التحديث جاي الشهر القادم.'],
        [5, 'كل درس قصير ومركّز، ما في حشو.', ReviewStatus::Pending, null],
        [2, 'الصوت في بعض الدروس منخفض.', ReviewStatus::Published, null],
        [5, 'أنصح فيها لأي حدا بده يشتغل فريلانس.', ReviewStatus::Published, null],
    ];

    public function run(): void
    {
        $leads = collect(self::LEADS)->map(fn (array $lead): Lead => Lead::forceCreate([
            'name' => $lead[0],
            'company' => $lead[1],
            'email' => $lead[2],
            'service' => $lead[3],
            'budget' => $lead[4],
            'stage' => $lead[5],
            'created_at' => now()->subDays($lead[6]),
            'updated_at' => now()->subDays($lead[6]),
            'note' => $lead[7],
        ]))->keyBy('company');

        $this->reviews();
        $this->conversations($leads->all());
    }

    private function reviews(): void
    {
        $owner = User::query()->oldest('id')->first();
        // one review per enrolment, spread over the first students and courses
        $enrollments = Enrollment::query()->with('student')->oldest('id')->get()->unique('student_id')->take(count(self::REVIEWS))->values();

        foreach ($enrollments as $i => $enrollment) {
            [$rating, $body, $status, $reply] = self::REVIEWS[$i];
            $date = now()->subDays(3 + $i * 3);

            Review::forceCreate([
                'student_id' => $enrollment->student_id,
                'course_id' => $enrollment->course_id,
                'rating' => $rating,
                'body' => $body,
                'status' => $status,
                'reply' => $reply,
                'replied_by' => $reply ? $owner?->id : null,
                'replied_at' => $reply ? $date->copy()->addDay() : null,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }

    /**
     * @param  array<string, Lead>  $leads  keyed by company
     */
    private function conversations(array $leads): void
    {
        $owner = User::query()->oldest('id')->first();
        $students = Enrollment::query()->with('student')->oldest('id')->get()->pluck('student')->unique('id')->values();

        $this->conversation(['student_id' => $students[0]->id, 'name' => $students[0]->name, 'email' => $students[0]->email], false, [
            [true, 'مرحباً أستاذ، عندي مشكلة في الدرس 38، الـ Middleware ما بيحوّل على صفحة الدخول.', now()->subMinutes(70)],
            [true, 'جربت أعيد تشغيل السيرفر وما زبطت.', now()->subMinutes(69)],
            [false, 'أهلاً، تأكد إن ملف middleware.js موجود في جذر المشروع مش داخل app.', now()->subMinutes(62)],
            [true, 'كان هذا السبب! شكراً جزيلاً 🙏', now()->subMinutes(58)],
        ], $owner);

        $rami = $leads['محمصة البن الذهبي'];
        $this->conversation(['lead_id' => $rami->id, 'name' => $rami->name, 'email' => $rami->email], false, [
            [true, 'صباح الخير، اطلعت على العرض. ممكن نضيف اشتراكات شهرية للقهوة؟', now()->subHours(3)],
        ], $owner);

        $rowad = $leads['حاضنة رواد'];
        $this->conversation(['lead_id' => $rowad->id, 'name' => $rowad->name, 'email' => $rowad->email], true, [
            [true, 'هل يمكن تقسيم الورشة على يومين بدل يوم واحد؟', now()->subDay()->setTime(14, 2)],
            [false, 'أكيد، يومين بـ 4 ساعات لكل يوم مناسبين أكثر للفريق.', now()->subDay()->setTime(14, 20)],
        ], $owner);

        $this->conversation(['student_id' => $students[1]->id, 'name' => $students[1]->name, 'email' => $students[1]->email], true, [
            [true, 'متى تبدأ الدفعة القادمة من برنامج المطوّر المستقل؟', now()->subDay()->setTime(11, 10)],
            [false, 'تبدأ 15 نوفمبر، والتسجيل مفتوح الآن.', now()->subDay()->setTime(11, 25)],
        ], $owner);

        $this->conversation(['name' => 'جنى عيسى', 'email' => 'jana.issa@mail.com'], true, [
            [true, 'هل في خصم للطلاب الجامعيين على الاشتراك؟', now()->subDays(6)->setTime(16, 40)],
        ], $owner);
    }

    /**
     * @param  array<string, mixed>  $contact
     * @param  list<array{bool, string, Carbon}>  $messages  [from contact, body, sent at]
     */
    private function conversation(array $contact, bool $read, array $messages, ?User $replier): void
    {
        $last = end($messages)[2];
        $conversation = Conversation::forceCreate([...$contact, 'read_at' => $read ? $last : null, 'last_message_at' => $last, 'created_at' => $messages[0][2]]);

        foreach ($messages as [$fromContact, $body, $at]) {
            $conversation->messages()->forceCreate([
                'from_contact' => $fromContact,
                'user_id' => $fromContact ? null : $replier?->id,
                'body' => $body,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }
}
