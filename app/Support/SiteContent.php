<?php

namespace App\Support;

use App\Models\SiteBlock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The public site's own content, edited on the dashboard's "محتوى الموقع" page: the announcement bar, the home
 * page, the about page, services and packages, testimonials, FAQs, and the headings and intros of
 * every page.
 *
 * Each section has a schema (fields and limits) that drives both the validation here and the editor in the
 * dashboard. Sections never edited fall back to resources/data/site-content.json (the site's original texts).
 */
class SiteContent
{
    public const CACHE_KEY = 'site.content';

    public const PHOTO_PATH = 'site/profile';

    /**
     * Where images uploaded for the content (the hanging badge photo) are kept, on the public disk.
     */
    public const IMAGES_DIR = 'site/images';

    /**
     * Icons the site can draw (src/components/ui/BaseIcon.vue in the site).
     */
    public const ICONS = [
        'code' => 'كود', 'globe' => 'كرة أرضية', 'briefcase' => 'حقيبة', 'monitor' => 'شاشة', 'clock' => 'ساعة',
        'users' => 'أشخاص', 'play' => 'تشغيل', 'calendar' => 'تقويم', 'article' => 'مقال', 'award' => 'شهادة',
        'star' => 'نجمة', 'chat' => 'محادثة', 'mail' => 'بريد', 'lock' => 'قفل', 'bulb' => 'فكرة', 'check' => 'صح',
        'link' => 'رابط', 'pin' => 'موقع', 'user' => 'شخص', 'search' => 'بحث',
    ];

    /**
     * Logos the site bundles for the technologies strip (simple-icons slugs).
     */
    public const TECHNOLOGIES = [
        'vuedotjs' => 'Vue.js', 'nuxt' => 'Nuxt', 'nextdotjs' => 'Next.js', 'react' => 'React', 'svelte' => 'Svelte',
        'javascript' => 'JavaScript', 'typescript' => 'TypeScript', 'nodedotjs' => 'Node.js', 'laravel' => 'Laravel',
        'php' => 'PHP', 'python' => 'Python', 'postgresql' => 'PostgreSQL', 'mysql' => 'MySQL', 'mongodb' => 'MongoDB',
        'redis' => 'Redis', 'prisma' => 'Prisma', 'supabase' => 'Supabase', 'firebase' => 'Firebase',
        'tailwindcss' => 'Tailwind CSS', 'html5' => 'HTML5', 'graphql' => 'GraphQL', 'docker' => 'Docker', 'git' => 'Git',
        'github' => 'GitHub', 'vercel' => 'Vercel', 'stripe' => 'Stripe', 'figma' => 'Figma', 'wordpress' => 'WordPress',
        'flutter' => 'Flutter',
    ];

    public const NETWORKS = [
        'github' => 'GitHub', 'linkedin' => 'LinkedIn', 'x' => 'X', 'youtube' => 'YouTube', 'instagram' => 'Instagram',
        'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'behance' => 'Behance', 'dribbble' => 'Dribbble',
    ];

    /**
     * An uploaded content image, as stored: a path under IMAGES_DIR.
     */
    private const IMAGE_PATTERN = '#^site/images/[A-Za-z0-9._-]+$#';

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    /**
     * Sections, in the editor's order. Field types: text, textarea, bool, icon, select (options), strings (a list of
     * short texts), tags (several options), list (repeated items with their own fields), object (fixed fields, which may nest).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        $text = fn (string $label, int $max = 120, bool $required = true, string $hint = ''): array => ['type' => 'text', 'label' => $label, 'max' => $max, 'required' => $required, 'hint' => $hint];
        $long = fn (string $label, int $max = 1000, bool $required = true): array => ['type' => 'textarea', 'label' => $label, 'max' => $max, 'required' => $required];
        $strings = fn (string $label, int $items, int $max = 120): array => ['type' => 'strings', 'label' => $label, 'max_items' => $items, 'max' => $max];
        $block = fn (string $label, array $fields): array => ['type' => 'object', 'label' => $label, 'fields' => $fields];
        $intro = fn (string $label, bool $withText = true): array => $block($label, [
            'eyebrow' => $text('العنوان الصغير', 40),
            'title' => $text('العنوان', 120),
            ...($withText ? ['text' => $long('المقدمة', 300)] : []),
        ]);

        return [
            // الرئيسية
            'announcement' => ['group' => 'home', 'label' => 'شريط الإعلان', 'hint' => 'السطر أعلى كل صفحات الموقع.', 'type' => 'object', 'fields' => [
                'enabled' => ['type' => 'bool', 'label' => 'إظهار الشريط'],
                'text' => $text('النص', 200, false),
                'link' => $text('الرابط', 255, false, 'صفحة في الموقع مثل /courses، أو رابط كامل'),
                'link_label' => $text('نص الرابط', 40, false),
            ]],
            'hero' => ['group' => 'home', 'label' => 'العنوان الرئيسي', 'hint' => 'الكلمات التي تتبدّل في عنوان الصفحة الرئيسية.', 'type' => 'object', 'fields' => [
                'words' => $strings('الكلمات المتبدّلة', 8, 30),
            ]],
            'technologies' => ['group' => 'about', 'label' => 'شريط الأدوات (صفحة من أنا)', 'type' => 'tags', 'options' => self::TECHNOLOGIES, 'max_items' => 30],
            'reasons' => ['group' => 'home', 'label' => 'لماذا تعمل معي', 'type' => 'strings', 'max_items' => 12, 'max' => 160],

            // عنك
            'profile' => ['group' => 'about', 'label' => 'التعريف', 'type' => 'object', 'fields' => [
                'name' => $text('الاسم', 60),
                'role' => $text('المسمّى', 80),
                'available' => $text('حالة التوفّر (خلف البطاقة المعلّقة في الرئيسية)', 60, false, 'مثل: متاح لمشاريع جديدة. اتركه فارغاً لإخفائه'),
                'short' => $long('النبذة القصيرة (الرئيسية)', 600),
                'story' => ['type' => 'strings', 'label' => 'القصة (صفحة من أنا) — فقرة لكل سطر', 'max_items' => 8, 'max' => 1000, 'long' => true],
                'cutout' => ['type' => 'image', 'label' => 'صورة البطاقة المعلّقة في الرئيسية', 'hint' => 'PNG أو WebP شفافة بدون خلفية (من remove.bg مثلاً)، من الصدر للأعلى والوجه في المنتصف، وتظهر فوق خلفية زرقاء. فارغة: يظهر أول حرف من اسمك.'],
            ]],
            'highlights' => ['group' => 'about', 'label' => 'ماذا أفعل', 'type' => 'list', 'max_items' => 6, 'title' => 'title', 'item' => [
                'icon' => ['type' => 'icon', 'label' => 'الأيقونة'],
                'title' => $text('العنوان', 60),
                'text' => $text('الوصف', 160),
            ]],
            'journey' => ['group' => 'about', 'label' => 'المسيرة', 'type' => 'list', 'max_items' => 10, 'title' => 'title', 'item' => [
                'label' => $text('المرحلة', 40),
                'title' => $text('العنوان', 60),
                'text' => $text('الوصف', 200),
            ]],
            'values' => ['group' => 'about', 'label' => 'قيمي في العمل', 'type' => 'list', 'max_items' => 8, 'title' => 'title', 'item' => [
                'title' => $text('العنوان', 60),
                'text' => $text('الوصف', 200),
            ]],
            'socials' => ['group' => 'about', 'label' => 'حساباتك', 'hint' => 'واتساب والبريد يُضبطان من الإعدادات ← عام.', 'type' => 'list', 'max_items' => 9, 'title' => 'network', 'item' => [
                'network' => ['type' => 'select', 'label' => 'المنصة', 'options' => self::NETWORKS],
                'url' => ['type' => 'url', 'label' => 'الرابط', 'max' => 255, 'required' => true],
            ]],

            // الخدمات
            'services' => ['group' => 'services', 'label' => 'الخدمات', 'hint' => 'تظهر في الرئيسية وصفحة الخدمات والقائمة، ونموذج "اطلب عرض سعر".', 'type' => 'list', 'max_items' => 12, 'title' => 'title', 'item' => [
                'id' => ['type' => 'text', 'label' => 'المعرّف (بالإنجليزية)', 'max' => 40, 'required' => true, 'pattern' => '/^[a-z0-9-]+$/', 'hint' => 'جزء الرابط /services#…'],
                'icon' => ['type' => 'icon', 'label' => 'الأيقونة'],
                'title' => $text('الاسم', 60),
                'text' => $text('الوصف', 200),
                'from' => $text('يبدأ من', 40, false),
                'duration' => $text('المدة', 40, false),
                'features' => $strings('ما تشمله', 8, 80),
            ]],
            'packages' => ['group' => 'services', 'label' => 'الباقات', 'type' => 'list', 'max_items' => 6, 'title' => 'title', 'item' => [
                'id' => ['type' => 'text', 'label' => 'المعرّف (بالإنجليزية)', 'max' => 40, 'required' => true, 'pattern' => '/^[a-z0-9-]+$/'],
                'label' => $text('الشارة', 30),
                'title' => $text('الاسم', 60),
                'price' => $text('السعر', 30),
                'price_note' => $text('بجانب السعر', 30, false, 'مثل: تبدأ من، أو / شهرياً'),
                'desc' => $text('الوصف', 200),
                'features' => $strings('ما تشمله', 8, 80),
                'popular' => ['type' => 'bool', 'label' => 'مميّزة (الأكثر طلباً)'],
            ]],
            'process' => ['group' => 'services', 'label' => 'مراحل العمل', 'type' => 'list', 'max_items' => 8, 'title' => 'title', 'item' => [
                'title' => $text('المرحلة', 60),
                'text' => $text('الوصف', 200),
            ]],

            // الآراء والأسئلة
            'testimonials' => ['group' => 'work', 'label' => 'آراء العملاء والطلاب', 'type' => 'list', 'max_items' => 20, 'title' => 'name', 'item' => [
                'name' => $text('الاسم', 60),
                'role' => $text('الصفة', 80),
                'text' => $long('الرأي', 500),
            ]],
            'faqs' => ['group' => 'work', 'label' => 'الأسئلة الشائعة', 'type' => 'list', 'max_items' => 20, 'title' => 'q', 'item' => [
                'q' => $text('السؤال', 160),
                'a' => $long('الجواب', 800),
            ]],

            // نصوص الصفحات
            'texts_home' => ['group' => 'texts', 'label' => 'نصوص الرئيسية', 'hint' => 'عناوين أقسام الصفحة الرئيسية ومقدّماتها.', 'type' => 'object', 'fields' => [
                'hero' => $block('العنوان الرئيسي', [
                    'before' => $text('قبل الكلمة المتبدّلة', 30),
                    'after' => $text('بعدها', 60),
                    'text' => $long('الوصف', 400),
                ]),
                'services' => $intro('الخدمات'),
                'why' => $intro('لماذا تعمل معي'),
                'about' => $block('من أنا', ['eyebrow' => $text('العنوان الصغير', 40)]),
                'courses' => $intro('الدورات'),
                'articles' => $intro('المقالات'),
                'workshop' => $intro('الورشة القادمة', false),
                'testimonials' => $intro('الآراء'),
                'faq' => $intro('الأسئلة الشائعة'),
                'newsletter' => $block('النشرة البريدية', [
                    'eyebrow' => $text('العنوان الصغير', 40),
                    'title' => $text('العنوان', 80),
                    'text' => $long('المقدمة', 300),
                    'perks' => $strings('المزايا تحت الحقل', 3, 40),
                ]),
                'cta' => $intro('الدعوة الأخيرة (أسفل الصفحة)'),
            ]],
            'texts_pages' => ['group' => 'texts', 'label' => 'نصوص الخدمات والتواصل ومن أنا', 'type' => 'object', 'fields' => [
                'services' => $block('صفحة الخدمات', ['text' => $long('المقدمة', 300)]),
                'packages' => $intro('الباقات', false),
                'process' => $intro('طريقة العمل', false),
                'contact' => $block('صفحة التواصل', [
                    'text' => $long('المقدمة', 300),
                    'eyebrow' => $text('نموذج المشروع: العنوان الصغير', 40),
                    'title' => $text('نموذج المشروع: العنوان', 120),
                    'student_title' => $text('نموذج الطالب: العنوان', 120),
                    'student_text' => $text('نموذج الطالب: الوصف', 200),
                    'general_title' => $text('الاستفسار العام: العنوان', 120),
                    'points' => ['type' => 'list', 'label' => 'النقاط بجانب النموذج', 'max_items' => 4, 'title' => 'title', 'item' => [
                        'icon' => ['type' => 'icon', 'label' => 'الأيقونة'],
                        'title' => $text('العنوان', 60),
                        'text' => $text('الوصف', 160),
                    ]],
                    'budgets' => ['type' => 'list', 'label' => 'خيارات الميزانية في النموذج', 'max_items' => 8, 'title' => 'label', 'item' => [
                        'label' => $text('كما تظهر للزائر', 40),
                        'amount' => ['type' => 'text', 'label' => 'المبلغ بالدولار (يُحفظ مع الطلب)', 'max' => 7, 'required' => true, 'pattern' => '/^[0-9]{1,7}$/', 'hint' => 'أعلى الفئة، أو بدايتها للفئة الأخيرة'],
                    ]],
                ]),
                'story' => $intro('من أنا: القصة', false),
                'values' => $intro('من أنا: طريقتي في العمل', false),
                'skills' => $intro('من أنا: الأدوات', false),
            ]],
            'texts_learning' => ['group' => 'texts', 'label' => 'نصوص الأكاديمية والموارد', 'type' => 'object', 'fields' => [
                'courses' => $block('صفحة الدورات', ['text' => $long('المقدمة', 300), 'badge' => $text('الميزة أسفل العنوان', 60)]),
                'paths' => $block('صفحة المسارات', ['text' => $long('المقدمة', 300), 'badge' => $text('الميزة أسفل العنوان', 60)]),
                'workshops' => $block('صفحة الورش', [
                    'text' => $long('المقدمة', 300),
                    'badges' => $strings('المزايا أسفل العنوان', 3, 60),
                    'private_title' => $text('عنوان الورش الخاصة', 80),
                    'private_text' => $text('نص الورش الخاصة', 200),
                ]),
                'articles' => $block('المقالات', [
                    'text' => $long('مقدمة صفحة المقالات', 300),
                    'badge' => $text('الميزة أسفل العنوان', 60),
                    'author_bio' => $text('نبذتك أسفل كل مقال (بعد المسمّى)', 200),
                    'newsletter_text' => $text('دعوة النشرة بجانب المقال', 120),
                    'related_eyebrow' => $text('مقالات ذات صلة: العنوان الصغير', 40),
                    'related_title' => $text('مقالات ذات صلة: العنوان', 80),
                ]),
                'tools' => $block('صفحة أدواتي', [
                    'text' => $long('المقدمة', 300),
                    'badge' => $text('الميزة أسفل العنوان', 60),
                    'note' => $text('ملاحظة روابط الإحالة', 200),
                ]),
            ]],
            'seo' => ['group' => 'texts', 'label' => 'محركات البحث والمشاركة', 'hint' => 'كيف يظهر موقعك في نتائج جوجل وعند مشاركة رابطه على واتساب وفيسبوك. يُثبَّت في الموقع عند نشره (npm run build).', 'type' => 'object', 'fields' => [
                'title' => $text('عنوان الموقع (تبويب المتصفح والرئيسية)', 70),
                'description' => $long('الوصف في نتائج البحث', 300),
                'share_description' => $text('الوصف عند المشاركة', 200),
            ]],
            'texts_ui' => ['group' => 'texts', 'label' => 'الأزرار والعناوين', 'hint' => 'أسماء الصفحات (تظهر في العنوان والقائمة والتذييل)، نصوص الأزرار، ورسائل الصفحات الفارغة.', 'type' => 'object', 'fields' => [
                'pages' => $block('أسماء الصفحات', [
                    'services' => $text('الخدمات', 30), 'about' => $text('من أنا', 30),
                    'courses' => $text('الدورات', 30), 'workshops' => $text('الورش', 30), 'paths' => $text('المسارات', 30), 'academy' => $text('مجموعة الدورات والورش', 30),
                    'articles' => $text('المقالات', 30), 'tools' => $text('الأدوات', 30), 'resources' => $text('مجموعة المقالات والأدوات', 30),
                    'contact' => $text('التواصل', 30),
                ]),
                'menu' => $block('وصف الصفحات في القائمة', [
                    'courses' => $text('الدورات', 80), 'workshops' => $text('الورش', 80), 'paths' => $text('المسارات', 80), 'articles' => $text('المقالات', 80), 'tools' => $text('الأدوات', 80),
                ]),
                'buttons' => $block('الأزرار', [
                    'quote' => $text('طلب عرض سعر (الرئيسية والقائمة)', 40),
                    'see_services' => $text('رابط الخدمات (الرئيسية)', 40),
                    'service_more' => $text('على بطاقة الخدمة', 40),
                    'request_service' => $text('أسفل الخدمات (الرئيسية)', 40),
                    'consult' => $text('لماذا تعمل معي', 40),
                    'read_story' => $text('من أنا (الرئيسية)', 40),
                    'ask' => $text('أسفل الأسئلة الشائعة', 40),
                    'start_project' => $text('الدعوة الأخيرة: الزر الأساسي', 40),
                    'start_learning' => $text('الدعوة الأخيرة: الزر الثاني', 40),
                    'all_courses' => $text('أسفل الدورات (الرئيسية)', 40),
                    'all_articles' => $text('أسفل المقالات (الرئيسية)', 40),
                    'order_service' => $text('صفحة الخدمات: على كل خدمة', 40),
                    'book_call' => $text('صفحة الخدمات: على كل باقة', 40),
                    'popular' => $text('شارة الباقة المميّزة', 30),
                    'about_work' => $text('من أنا: الزر الأساسي', 40),
                    'about_learn' => $text('من أنا: الزر الثاني', 40),
                    'all_tools' => $text('من أنا: أسفل الأدوات', 60),
                    'private_workshop' => $text('الورش الخاصة', 40),
                    'subscribe' => $text('الاشتراك في النشرة', 40),
                    'about_author' => $text('أسفل كل مقال (عنك)', 40),
                    'enroll' => $text('صفحة الدورة: التسجيل', 40),
                    'book_seat' => $text('بطاقة الورشة: الحجز', 40),
                    'seats_full' => $text('بطاقة الورشة: اكتملت', 40),
                ]),
                'empty' => $block('عند عدم وجود محتوى', [
                    'courses' => $text('الدورات', 120), 'workshops' => $text('الورش', 120), 'tools' => $text('الأدوات', 120),
                ]),
                'not_found' => $block('صفحة غير موجودة (404)', ['text' => $text('النص', 160), 'button' => $text('الزر', 40)]),
            ]],
            'texts_general' => ['group' => 'texts', 'label' => 'التذييل وصفحات الدخول', 'type' => 'object', 'fields' => [
                'footer' => $block('التذييل', ['text' => $text('النبذة (بعد المسمّى)', 200)]),
                'maintenance' => $block('رسالة وضع الصيانة', ['title' => $text('العنوان', 60), 'text' => $text('النص', 200)]),
                'login' => $block('تسجيل الدخول', ['title' => $text('العنوان', 60), 'text' => $text('المقدمة', 160)]),
                'register' => $block('إنشاء حساب', ['title' => $text('العنوان', 60), 'text' => $text('المقدمة', 160)]),
                'auth' => $block('الجانب الملوّن في صفحتي الدخول والتسجيل', [
                    'title' => $text('العنوان', 120),
                    'perks' => ['type' => 'list', 'label' => 'المزايا', 'max_items' => 6, 'title' => 'text', 'item' => [
                        'icon' => ['type' => 'icon', 'label' => 'الأيقونة'],
                        'text' => $text('النص', 80),
                    ]],
                    'quote' => $long('الاقتباس', 300),
                    'quote_by' => $text('صاحب الاقتباس', 80),
                ]),
            ]],
        ];
    }

    /**
     * The editor's tabs.
     *
     * @return array<string, string>
     */
    public static function groups(): array
    {
        return ['home' => 'الرئيسية', 'about' => 'عنك', 'services' => 'الخدمات والباقات', 'work' => 'الآراء والأسئلة', 'texts' => 'نصوص الصفحات'];
    }

    /**
     * Every section: what was saved, else the site's original text; plus the uploaded photo.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $saved = Cache::rememberForever(self::CACHE_KEY, fn (): array => SiteBlock::query()->pluck('value', 'key')->map(fn ($value) => is_string($value) ? json_decode($value, true) : $value)->all());
        $defaults = self::defaults();

        $values = [];
        foreach (self::definitions() as $key => $definition) {
            $values[$key] = isset($saved[$key]) ? self::withDefaults($definition, $saved[$key], $defaults[$key]) : $defaults[$key];
        }
        $values['photo'] = isset($saved['photo']['path']) ? Storage::disk('public')->url($saved['photo']['path']) : null;

        return $this->values = $values;
    }

    /**
     * What the public site reads: every section with its images as full URLs.
     *
     * @return array<string, mixed>
     */
    public function forSite(): array
    {
        $values = $this->all();
        foreach (self::definitions() as $key => $definition) {
            $values[$key] = self::mapImages($definition, $values[$key], fn (?string $path): ?string => $path ? Storage::disk('public')->url($path) : null);
        }

        return $values;
    }

    /**
     * Save one section (already validated): only the fields the schema knows are kept. Images it no longer uses
     * are deleted.
     */
    public function update(string $key, mixed $value): mixed
    {
        $before = $this->imagePaths();
        $clean = self::clean(self::definitions()[$key], $value);
        SiteBlock::query()->updateOrCreate(['key' => $key], ['value' => $clean]);
        $this->forget();
        $this->deleteUnusedImages($before);

        return $clean;
    }

    /**
     * Back to the site's original text.
     */
    public function reset(string $key): void
    {
        $before = $this->imagePaths();
        SiteBlock::query()->whereKey($key)->delete();
        $this->forget();
        $this->deleteUnusedImages($before);
    }

    /**
     * Every uploaded image the content uses now.
     *
     * @return list<string>
     */
    private function imagePaths(): array
    {
        $paths = [];
        foreach (self::definitions() as $key => $definition) {
            self::mapImages($definition, $this->all()[$key], function (?string $path) use (&$paths): ?string {
                if ($path) {
                    $paths[] = $path;
                }

                return $path;
            });
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  list<string>  $before
     */
    private function deleteUnusedImages(array $before): void
    {
        $unused = array_diff($before, $this->imagePaths());
        if ($unused) {
            Storage::disk('public')->delete(array_values($unused));
        }
    }

    /**
     * Runs $map over every image path in a value, following its schema.
     *
     * @param  array<string, mixed>  $definition
     * @param  callable(?string): ?string  $map
     */
    private static function mapImages(array $definition, mixed $value, callable $map): mixed
    {
        return match ($definition['type']) {
            'image' => $map($value),
            'object' => is_array($value) ? collect($definition['fields'])->map(fn (array $field, string $name): mixed => self::mapImages($field, $value[$name] ?? null, $map))->all() : $value,
            'list' => is_array($value) ? array_map(fn ($item): mixed => is_array($item) ? [...$item, ...collect($definition['item'])->map(fn (array $field, string $name): mixed => self::mapImages($field, $item[$name] ?? null, $map))->all()] : $item, $value) : $value,
            default => $value,
        };
    }

    public function setPhoto(?string $path): void
    {
        $old = SiteBlock::query()->find('photo')?->value['path'] ?? null;

        if ($path === null) {
            SiteBlock::query()->whereKey('photo')->delete();
        } else {
            SiteBlock::query()->updateOrCreate(['key' => 'photo'], ['value' => ['path' => $path]]);
        }

        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }
        $this->forget();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->values = null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return json_decode((string) file_get_contents(resource_path('data/site-content.json')), true);
    }

    /**
     * Laravel rules for one section, from its schema, under the "value" key of the request.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, list<mixed>>
     */
    public static function rules(array $definition, string $path = 'value'): array
    {
        $required = ($definition['required'] ?? false) ? 'required' : 'nullable';

        return match ($definition['type']) {
            'object' => [$path => ['required', 'array'], ...collect($definition['fields'])->flatMap(fn (array $field, string $name): array => self::rules($field, "{$path}.{$name}"))->all()],
            'list' => [$path => ['present', 'array', 'max:'.$definition['max_items']], ...collect($definition['item'])->flatMap(fn (array $field, string $name): array => self::rules($field, "{$path}.*.{$name}"))->all()],
            'strings' => [$path => ['present', 'array', 'max:'.$definition['max_items']], "{$path}.*" => ['required', 'string', 'max:'.$definition['max']]],
            'tags' => [$path => ['present', 'array', 'max:'.$definition['max_items']], "{$path}.*" => ['distinct', Rule::in(array_keys($definition['options']))]],
            'text', 'textarea' => [$path => [$required, 'string', 'max:'.($definition['max'] ?? 1000), ...(isset($definition['pattern']) ? ['regex:'.$definition['pattern']] : [])]],
            'url' => [$path => [$required, 'string', 'max:255', 'url:http,https']],
            'bool' => [$path => ['boolean']],
            'image' => [$path => ['nullable', 'string', 'max:255', 'regex:'.self::IMAGE_PATTERN]],
            'icon' => [$path => ['required', Rule::in(array_keys(self::ICONS))]],
            'select' => [$path => ['required', Rule::in(array_keys($definition['options']))]],
        };
    }

    /**
     * Field names for the validation messages ("حقل المعرّف مطلوب" rather than "value.3.id").
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, string>
     */
    public static function attributes(array $definition, string $path = 'value'): array
    {
        $own = [$path => $definition['label'] ?? ''];

        return match ($definition['type']) {
            'object' => [...$own, ...collect($definition['fields'])->flatMap(fn (array $field, string $name): array => self::attributes($field, "{$path}.{$name}"))->all()],
            'list' => [...$own, ...collect($definition['item'])->flatMap(fn (array $field, string $name): array => self::attributes($field, "{$path}.*.{$name}"))->all()],
            'strings', 'tags' => [...$own, "{$path}.*" => $definition['label']],
            default => $own,
        };
    }

    /**
     * A section saved before a field was added to it gets that field's original text, so the site never meets a
     * missing key.
     *
     * @param  array<string, mixed>  $definition
     */
    private static function withDefaults(array $definition, mixed $value, mixed $default): mixed
    {
        // items of a list saved before a field existed: that field starts empty
        if ($definition['type'] === 'list' && is_array($value)) {
            return array_map(fn ($item): mixed => is_array($item)
                ? collect($definition['item'])->map(fn (array $field, string $name): mixed => array_key_exists($name, $item) ? self::withDefaults($field, $item[$name], null) : self::blank($field))->all()
                : $item, $value);
        }

        if ($definition['type'] !== 'object' || ! is_array($value)) {
            return $value;
        }

        return collect($definition['fields'])->map(fn (array $field, string $name): mixed => array_key_exists($name, $value)
            ? self::withDefaults($field, $value[$name], $default[$name] ?? null)
            : ($default[$name] ?? null))->all();
    }

    /**
     * An empty value of a field's type.
     *
     * @param  array<string, mixed>  $definition
     */
    private static function blank(array $definition): mixed
    {
        return match ($definition['type']) {
            'object' => collect($definition['fields'])->map(fn (array $field): mixed => self::blank($field))->all(),
            'list', 'strings', 'tags' => [],
            'bool' => false,
            'image', 'url' => null,
            default => '',
        };
    }

    /**
     * Keeps only the fields the schema knows, with the right types (no stray keys reach the site).
     *
     * @param  array<string, mixed>  $definition
     */
    private static function clean(array $definition, mixed $value): mixed
    {
        return match ($definition['type']) {
            'object' => collect($definition['fields'])->map(fn (array $field, string $name): mixed => self::clean($field, $value[$name] ?? null))->all(),
            'list' => array_values(array_map(fn ($item): array => collect($definition['item'])->map(fn (array $field, string $name): mixed => self::clean($field, $item[$name] ?? null))->all(), $value ?? [])),
            'strings', 'tags' => array_values(array_map('strval', $value ?? [])),
            'bool' => (bool) $value,
            default => $value === null ? null : (string) $value,
        };
    }
}
