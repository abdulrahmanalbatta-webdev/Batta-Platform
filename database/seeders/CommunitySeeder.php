<?php

namespace Database\Seeders;

use App\Enums\LeadStage;
use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Review;
use App\Models\Student;
use App\Models\User;
use App\Notifications\Alerts\ContactMessageReceived;
use App\Notifications\Alerts\LeadReceived;
use App\Notifications\Alerts\OrderPaid;
use App\Notifications\Alerts\ReviewSubmitted;
use App\Support\TeamAlerts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Sample project requests, course reviews and conversations for local development. Runs after SalesSeeder:
 * reviews come from real enrolments, two conversations belong to students, and the bell gets a few notifications.
 */
class CommunitySeeder extends Seeder
{
    /**
     * @var list<array{string, string, string, string, int, LeadStage, int, string}>
     */
    private const LEADS = [
        ['آدم عودة', 'شركة آدم', 'adam@adam.co', 'websites', 400, LeadStage::Lost, 26, 'اختار حلاً جاهزاً'],
        ['مريم سالم', 'أكاديمية مريم', 'maryam@academy.co', 'web-apps', 5200, LeadStage::Won, 22, 'منصة دورات داخلية'],
        ['زيد النجار', 'متجر زيد', 'zaid@store.co', 'maintenance', 300, LeadStage::Won, 16, 'اشتراك صيانة شهري'],
        ['سلمى العلي', 'مطعم البيت', 'salma@albeit.co', 'websites', 600, LeadStage::Proposal, 12, 'قائمة طعام وحجز طاولات'],
        ['عمر قاسم', 'لوجستك برو', 'omar@logistic.pro', 'dashboards', 6500, LeadStage::Proposal, 10, 'لوحة تتبع شحنات'],
        ['نور الشريف', 'استوديو نور', 'noor@studio.co', 'websites', 800, LeadStage::Contacted, 7, 'موقع معرض أعمال'],
        ['خالد منصور', 'حاضنة رواد', 'khaled@rowad.org', 'training', 1200, LeadStage::Contacted, 5, 'ورشة يومين لفريق 18 شخصاً'],
        ['د. هبة يونس', 'عيادة الابتسامة', 'heba@smile.clinic', 'web-apps', 4000, LeadStage::New, 3, 'نظام حجوزات وتذكير'],
        ['رامي حمدان', 'محمصة البن الذهبي', 'rami@goldenbean.co', 'ecommerce', 2500, LeadStage::New, 2, 'متجر مع اشتراكات شهرية'],
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
            // won and lost requests were decided a few days after they came in
            'decided_at' => $lead[5]->isOpen() ? null : now()->subDays($lead[6] - 4),
            'created_at' => now()->subDays($lead[6]),
            'updated_at' => now()->subDays($lead[6]),
            'note' => $lead[7],
        ]))->keyBy('company');

        $this->reviews();
        $this->comments();
        $this->conversations($leads->all());
        $this->alerts();
    }

    /**
     * A few bell notifications for the latest events (seeders run without model events, so the observers stay quiet).
     */
    private function alerts(): void
    {
        $alerts = [
            [new OrderPaid(Order::query()->where('status', OrderStatus::Completed)->latest('paid_at')->firstOrFail()), now()->subMinutes(5), false],
            [new ContactMessageReceived(ConversationMessage::query()->where('from_contact', true)->latest()->firstOrFail()), now()->subMinutes(58), false],
            [new LeadReceived(Lead::query()->latest()->firstOrFail()), now()->subDays(2), false],
            [new ReviewSubmitted(Review::query()->where('status', ReviewStatus::Pending)->latest()->firstOrFail()), now()->subDays(3), true],
        ];

        foreach ($alerts as [$alert, $at, $read]) {
            foreach (TeamAlerts::recipients($alert) as $member) {
                // the bell entry only: no emails from seeding
                $member->notifyNow($alert, ['database']);
                $member->notifications()->latest()->first()->forceFill(['created_at' => $at, 'updated_at' => $at, 'read_at' => $read ? $at : null])->save();
            }
        }
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
     * A few comments on the latest published articles: published ones (one with a reply) and one waiting.
     */
    private function comments(): void
    {
        $owner = User::query()->oldest('id')->first();
        $articles = Article::query()->published()->latest('published_at')->take(2)->get();
        $students = Student::query()->oldest('id')->take(4)->get();
        if ($articles->isEmpty() || $students->count() < 4) {
            return;
        }

        $comments = [
            [0, 0, 'شرح واضح جداً، طبّقت الخطوات على مشروعي واشتغلت من أول مرة.', ReviewStatus::Published, 'سعيد أنه أفادك، بالتوفيق في مشروعك!', 6],
            [0, 1, 'هل في طريقة لعمل نفس الشيء مع Laravel بدل Node؟', ReviewStatus::Published, null, 4],
            [1, 2, 'مقال رائع، ياريت تكتب عن النشر على سيرفر خاص.', ReviewStatus::Published, null, 2],
            [1, 3, 'شكراً على المقال، عندي سؤال عن التسعير بالساعة مقابل المشروع.', ReviewStatus::Pending, null, 1],
        ];
        foreach ($comments as [$article, $student, $body, $status, $reply, $days]) {
            $date = now()->subDays($days);
            ArticleComment::forceCreate([
                'article_id' => $articles[$article % $articles->count()]->id,
                'student_id' => $students[$student]->id,
                'body' => $body,
                'status' => $status,
                'reply' => $reply,
                'replied_by' => $reply ? $owner?->id : null,
                'replied_at' => $reply ? $date->copy()->addHours(5) : null,
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
