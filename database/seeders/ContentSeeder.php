<?php

namespace Database\Seeders;

use App\Actions\SyncCurriculum;
use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Enums\CourseCategory;
use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\WorkshopFormat;
use App\Models\Article;
use App\Models\Course;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\Workshop;
use Illuminate\Database\Seeder;

/**
 * The dashboard's sample courses, workshops, articles and tools, for local development.
 */
class ContentSeeder extends Seeder
{
    public function run(SyncCurriculum $curriculum): void
    {
        $this->tools();
        $this->workshops();
        $this->articles();
        $this->courses($curriculum);
    }

    private function tools(): void
    {
        $categories = collect([
            'editor' => ['المحرر', '#0066ff'],
            'frontend' => ['الواجهات', '#0891b2'],
            'backend' => ['الخلفية', '#0e9f6e'],
            'deploy' => ['النشر', '#0b0d12'],
            'design' => ['التصميم', '#7c3aed'],
            'productivity' => ['الإنتاجية', '#c27803'],
        ])->map(fn (array $category, string $key) => ToolCategory::forceCreate([
            'name' => $category[0],
            'color' => $category[1],
            'position' => array_search($key, ['editor', 'frontend', 'backend', 'deploy', 'design', 'productivity'], true),
        ]));

        $tools = [
            ['VS Code', 'VS', '#0066ff', 'editor', 'محرري الأساسي مع إعدادات مشتركة في كل مستودع.', 2019, 'https://code.visualstudio.com', false, true, 1240],
            ['Cursor', 'Cu', '#0b0d12', 'editor', 'للمهام المتكررة وإعادة الهيكلة بمساعدة AI.', 2024, 'https://cursor.com', false, true, 980],
            ['Next.js', 'N', '#0b0d12', 'frontend', 'إطار العمل الافتراضي لكل مشاريع العملاء.', 2021, 'https://nextjs.org', false, true, 1610],
            ['Vue', 'V', '#0e9f6e', 'frontend', 'للواجهات التفاعلية والمشاريع التي تحتاج بساطة وسرعة.', 2020, 'https://vuejs.org', false, true, 870],
            ['Tailwind CSS', 'tw', '#0891b2', 'frontend', 'تصميم سريع ومتسق بدون ملفات CSS ضخمة.', 2021, 'https://tailwindcss.com', false, true, 760],
            ['Node.js', 'JS', '#0e9f6e', 'backend', 'للـ APIs والمهام الخلفية.', 2020, 'https://nodejs.org', false, true, 540],
            ['PostgreSQL', 'PG', '#334155', 'backend', 'قاعدة البيانات الأولى لأي مشروع جاد.', 2020, 'https://postgresql.org', false, true, 610],
            ['Vercel', '▲', '#0b0d12', 'deploy', 'نشر تلقائي مع كل دفعة إلى GitHub.', 2021, 'https://vercel.com', true, true, 1930],
            ['GitHub Actions', 'GH', '#334155', 'deploy', 'اختبارات تلقائية قبل كل دمج.', 2022, 'https://github.com/features/actions', false, true, 380],
            ['Figma', 'Fg', '#7c3aed', 'design', 'تصميم الواجهات ومشاركتها مع العميل.', 2020, 'https://figma.com', false, true, 690],
            ['Lemon Squeezy', 'LS', '#c27803', 'productivity', 'بيع الدورات وتحصيل المدفوعات دولياً.', 2025, 'https://lemonsqueezy.com', true, true, 1150],
            ['Supabase', 'SB', '#0e9f6e', 'backend', 'قاعدة بيانات ومصادقة جاهزة للمشاريع السريعة.', 2025, 'https://supabase.com', false, false, 0],
        ];

        foreach ($tools as $position => [$name, $short, $color, $category, $why, $since, $url, $affiliate, $published, $clicks]) {
            Tool::forceCreate([
                'tool_category_id' => $categories[$category]->id,
                'name' => $name, 'short' => $short, 'color' => $color, 'why' => $why, 'since' => $since, 'url' => $url,
                'is_affiliate' => $affiliate, 'is_published' => $published, 'clicks' => $clicks, 'position' => $position,
            ]);
        }
    }

    private function workshops(): void
    {
        $workshops = [
            ['ابنِ ملف أعمالك في ساعتين', 11, '19:00', WorkshopFormat::Online, 'Zoom', 0, 100],
            ['Server Actions في Next.js عملياً', 25, '19:00', WorkshopFormat::Online, 'Zoom', 19, 40],
            ['يوم كامل: من الفكرة إلى منتج منشور', 37, '10:00', WorkshopFormat::InPerson, 'عمّان', 49, 25],
            ['ورشة خاصة: فريق حاضنة الأعمال', 46, '10:00', WorkshopFormat::InPerson, 'رام الله', 1200, 18],
            ['مقدمة في Git للطلاب', -17, '18:00', WorkshopFormat::Online, 'Zoom', 0, 120],
        ];

        foreach ($workshops as [$title, $inDays, $time, $format, $place, $price, $seats]) {
            Workshop::create([
                'title' => $title, 'date' => now()->addDays($inDays)->toDateString(), 'start_time' => $time,
                'format' => $format, 'place' => $place, 'price' => $price, 'seats' => $seats,
            ]);
        }
    }

    private function articles(): void
    {
        $body = "## المقدمة\n\nهذا مقال تجريبي من بيانات التطوير المحلية. ".str_repeat('نكتب هنا فقرة توضيحية قصيرة عن الفكرة الأساسية للمقال. ', 6)
            ."\n\n## الخطوة الأولى\n\n- نقطة أولى\n- نقطة ثانية\n\n```js\nconsole.log('مرحباً')\n```";

        $articles = [
            ['بناء نظام مصادقة كامل في Next.js خطوة بخطوة', ArticleCategory::Tutorials, ArticleStatus::Published, -3, 8420],
            ['كيف سلّمت موقع مطعم في 14 يوماً', ArticleCategory::BehindTheScenes, ArticleStatus::Published, -10, 5210],
            ['كيف تسعّر أول مشروع عمل حر لك', ArticleCategory::Freelancing, ArticleStatus::Published, -17, 12890],
            ['أدواتي اليومية كمطوّر في 2026', ArticleCategory::ToolsAndAi, ArticleStatus::Published, -24, 6730],
            ['PostgreSQL للمبتدئين: الفهارس بلغة بسيطة', ArticleCategory::Tutorials, ArticleStatus::Published, -31, 4120],
            ['Server Actions: متى تستخدمها ومتى تتجنبها', ArticleCategory::Tutorials, ArticleStatus::Scheduled, 4, 0],
            ['كيف تكتب عرض سعر يقنع العميل', ArticleCategory::Freelancing, ArticleStatus::Draft, 0, 0],
        ];

        foreach ($articles as $index => [$title, $category, $status, $days, $views]) {
            Article::forceCreate([
                'title' => $title,
                'slug' => 'sample-article-'.($index + 1),
                'excerpt' => 'ملخص المقال كما يظهر في بطاقة المقال على الموقع.',
                'body' => $body,
                'category' => $category,
                'status' => $status,
                'publish_at' => $status === ArticleStatus::Scheduled ? now()->addDays($days)->setTime(9, 0) : null,
                'published_at' => $status === ArticleStatus::Published ? now()->addDays($days) : null,
                'views' => $views,
            ]);
        }
    }

    private function courses(SyncCurriculum $curriculum): void
    {
        $courses = [
            ['Next.js من الصفر إلى الإنتاج', 'nextjs-production', CourseLevel::Intermediate, CourseCategory::Frontend, CourseStatus::Published, 79],
            ['أساسيات الويب الحديث', 'modern-web-basics', CourseLevel::Beginner, CourseCategory::Frontend, CourseStatus::Published, 39],
            ['برنامج المطوّر المستقل', 'freelance-developer', CourseLevel::Advanced, CourseCategory::Freelancing, CourseStatus::Published, 249],
            ['Git و GitHub للفرق', 'git-github-teams', CourseLevel::Beginner, CourseCategory::FullStack, CourseStatus::Published, 0],
            ['APIs باستخدام Node و PostgreSQL', 'node-postgres-apis', CourseLevel::Intermediate, CourseCategory::Backend, CourseStatus::Published, 69],
            ['Vue 3 عملياً: من المكونات إلى المتجر', 'vue-3-in-practice', CourseLevel::Intermediate, CourseCategory::Frontend, CourseStatus::Draft, 59],
            ['TypeScript للمطورين', 'typescript-for-developers', CourseLevel::Intermediate, CourseCategory::FullStack, CourseStatus::Review, 49],
        ];

        foreach ($courses as [$title, $slug, $level, $category, $status, $price]) {
            $course = Course::forceCreate([
                'title' => $title, 'slug' => $slug, 'short_description' => 'دورة عملية تنتهي بمشروع حقيقي منشور.',
                'outcomes' => ['بناء مشروع كامل', 'نشر المشروع على الإنترنت'], 'tags' => ['مشروع عملي'],
                'level' => $level, 'category' => $category, 'status' => $status, 'price' => $price,
            ]);

            $curriculum->handle($course, [
                ['title' => 'البداية والتجهيز', 'lessons' => [['title' => 'مقدمة الدورة', 'duration' => '05:20'], ['title' => 'تجهيز بيئة العمل', 'duration' => '12:40']]],
                ['title' => 'المشروع الأول', 'lessons' => [['title' => 'هيكلة المشروع', 'duration' => '18:05'], ['title' => 'النشر', 'duration' => '09:30']]],
            ]);
        }
    }
}
