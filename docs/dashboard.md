# لوحة تحكم Batta داخل Laravel

الواجهات صارت Blade في `resources/views` ومقسمة حسب الكيان. التنسيقات والسكربتات والصور في `public/assets/dashboard`.
مربوط بقاعدة البيانات عبر الـ API: الدخول، الملف الشخصي، الفريق، الأجهزة المتصلة، الدورات (مع المنهج)، الورش، المقالات، الأدوات وتصنيفاتها، الطلاب، الطلبات والكوبونات، الرسائل، التقييمات، طلبات المشاريع، جرس الإشعارات، سجل النشاط، والبحث العام.
والرئيسية والتحليلات بأرقام حقيقية من المبيعات والطلاب. ما ضل في بيانات تجريبية (`data.js` انحذف)، والإعدادات (عام، الدفع، البريد، الإشعارات، الأمان) محفوظة بقاعدة البيانات ومطبّقة.

## التشغيل

يحتاج PHP 8.4 أو أحدث مع `pdo_mysql`، وMySQL 8 (القاعدة الرسمية للمشروع؛ الـ CI بيشغّل الاختبارات على MySQL وSQLite).
قبل أول تشغيل أنشئ القاعدة وعدّل `DB_*` بـ `.env`:

```sql
CREATE DATABASE batta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

للتجربة السريعة بدون MySQL: `DB_CONNECTION=sqlite` واحذف باقي أسطر `DB_`.

```bash
composer run setup          # أول مرة: المكتبات، .env، الجداول، رابط storage
php artisan db:seed         # فريق تجريبي محلي
php artisan serve
```

ثم افتح `http://localhost:8000/dashboard` وادخل بـ `admin@batta.dev` / `password` (من الـ seeder، للتطوير فقط).

على الخادم الحقيقي لا تستخدم الـ seeder، أنشئ المالك بأمر يسألك عن كلمة المرور:

```bash
php artisan app:create-owner admin@batta.dev "عبدالرحمن البطة"
```

قائمة "الأجهزة المتصلة" تحتاج `SESSION_DRIVER=database` (الافتراضي في `.env.example`).

### عمليات بالخلفية

- **الإيميلات** (رسائل الطلاب، الفواتير، تذكير الورش، الردود على المحادثات، وإيميلات التنبيهات) بتنبعت عن طريق الـ queue (`QUEUE_CONNECTION=database`)، فلازم يكون في worker شغال:
  محلياً `composer run dev` (بيشغّل السيرفر والـ queue والـ logs مع بعض) أو `php artisan queue:work` بنافذة لحاله، وعلى الخادم `queue:work` تحت Supervisor أو ما يشبهه.
- **المقالات المجدولة** بتنشر بأمر `articles:publish-scheduled` كل دقيقة، وهذا يحتاج الـ scheduler:
  محلياً `php artisan schedule:work`، وعلى الخادم سطر cron واحد يشغّل `php artisan schedule:run` كل دقيقة.
  نفس الـ scheduler بيحذف يومياً سجل النشاط الأقدم من سنة، والإشعارات المقروءة الأقدم من 90 يوم، ونسخ البيانات الأقدم من 7 أيام، وبيبعت التقرير الأسبوعي كل أحد الساعة 8 (`reports:weekly`).
- **تصدير البيانات** كمان Job على الـ queue، فبدون worker النسخة ما بتجهز.

## الواجهات (resources/views)

```
resources/views/
├── layouts/
│   ├── dashboard.blade.php        هيكل صفحات اللوحة (القائمة والشريط يبنيهما app.js حول #content)
│   ├── guest.blade.php            صفحات بدون قائمة (الدخول، الأخطاء)
│   └── partials/app-config.blade.php   يمرر روابط الـ routes ومسار الملفات إلى JavaScript (window.APP)
├── dashboard/index.blade.php      لوحة المعلومات
├── analytics/index.blade.php      التحليلات
├── courses/   index · form        الدورات + إضافة/تعديل دورة
├── workshops/ index               الورش
├── articles/  index · form        المقالات + محرر المقال
├── tools/     index               الأدوات وتصنيفاتها
├── orders/    index               الطلبات
├── coupons/   index               الكوبونات
├── leads/     index               طلبات المشاريع
├── students/  index               الطلاب
├── messages/  index               الرسائل
├── reviews/   index               التقييمات
├── settings/  index               الإعدادات
├── profile/   index               الملف الشخصي
├── auth/login.blade.php           تسجيل الدخول
└── errors/404.blade.php           صفحة غير موجودة (يستخدمها Laravel تلقائياً)
```

كل واجهة تحدد عنوانها واسم صفحتها، وتضع محتواها وأي نوافذ منبثقة وسكربتات خاصة بها:

```blade
@extends('layouts.dashboard')

@section('title', 'الدورات')
@section('page', 'courses')        {{-- يحدد الرابط النشط في القائمة --}}

@section('content') ... @endsection
@push('modals') ... @endpush
@push('scripts') <script src="{{ asset('assets/dashboard/js/pages/courses.js') }}"></script> @endpush
```

## الروابط (routes/web.php)

| الرابط | الاسم | الواجهة |
|---|---|---|
| `/dashboard` | `dashboard` | `dashboard.index` |
| `/dashboard/analytics` | `analytics` | `analytics.index` |
| `/dashboard/courses` · `/create` · `/{id}/edit` | `courses.index` · `courses.create` · `courses.edit` | `courses.index` · `courses.form` |
| `/dashboard/workshops` | `workshops.index` | `workshops.index` |
| `/dashboard/articles` · `/create` · `/{id}/edit` | `articles.index` · `articles.create` · `articles.edit` | `articles.index` · `articles.form` |
| `/dashboard/tools` | `tools.index` | `tools.index` |
| `/dashboard/orders` | `orders.index` | `orders.index` |
| `/dashboard/coupons` | `coupons.index` | `coupons.index` |
| `/dashboard/leads` | `leads.index` | `leads.index` |
| `/dashboard/students` | `students.index` | `students.index` |
| `/dashboard/messages` | `messages.index` | `messages.index` |
| `/dashboard/reviews` | `reviews.index` | `reviews.index` |
| `/dashboard/settings` | `settings.index` | `settings.index` |
| `/dashboard/profile` | `profile` | `profile.index` |
| `/login` | `login` | `auth.login` (للزوار فقط) |
| `/reset-password/{token}` | `password.reset` | `auth.reset-password` (رابط الاستعادة ورابط الدعوة `?invite=1`) |

كل روابط `/dashboard` تحتاج تسجيل دخول، والزائر يتحول إلى `/login` ثم يرجع للصفحة اللي كان بدها بعد الدخول.

في صفحات التعديل يصل `{id}` إلى الواجهة، فيضعه الـ layout في `<body data-id="...">` ليقرأه سكربت الصفحة.

## الملفات الثابتة (public/assets/dashboard)

```
public/assets/dashboard/
├── css/style.css        نظام التصميم كاملاً
├── js/
│   ├── boot.js          يعمل قبل أول رسم: هيكل اللوحة + حالة طي القائمة + تحميل الخطوط
│   ├── app.js           التخطيط + المكونات المشتركة (window.App)
│   ├── charts.js        رسوم SVG بدون مكتبات (window.Charts)
│   └── pages/*.js       منطق كل صفحة
├── img/                 الشعار والصور
└── fonts/               خط Cairo
```

### الروابط داخل JavaScript

لا تكتب `href="courses.html"`. استخدم أسماء الروابط المعرّفة في `app-config.blade.php`:

```js
App.url('courses')                       // /dashboard/courses
App.url('course-edit', { id: 'C-101' })  // /dashboard/courses/C-101/edit
App.url('students', { q: 'محمد' })        // /dashboard/students?q=محمد
App.url('tools', {}, 'new')              // /dashboard/tools#new
App.asset('img/logo.png')                // /assets/dashboard/img/logo.png
```

### المكونات المشتركة في `App`

- `DataTable`: جدول فيه بحث وفرز وفلاتر وترقيم وتحديد.
- `openModal` / `closeModal`: نوافذ منبثقة. يكفي `data-open="id"` و `data-close` في HTML.
- `openDrawer` / `closeDrawer`: درج جانبي.
- `confirmDialog({ title, text, ok })`: نافذة تأكيد تُرجع `Promise<boolean>`.
- `toast(msg, type)`: إشعار سريع.
- القوائم المنسدلة `data-dropdown`، والتبويبات `data-tabs` / `data-tab` / `data-panel`.
- `downloadCSV(file, columns, rows)`.
- `copy(text, message)`: نسخ نص مع بديل للمتصفحات التي لا تدعم الحافظة.
- `setNavCount(page, n)`: تحديث العدّاد بجانب رابط الصفحة في القائمة الجانبية.
- الأيقونات: `<i data-icon="name"></i>` تتحول تلقائياً إلى SVG.

## الـ API (routes/api.php)

كل نقاط الـ API تحت `/dashboard/api/v1` وأسماؤها تبدأ بـ `api.` (مثلاً `api.status`).
تُحمَّل بوسيط `web` من `bootstrap/app.php`، يعني بتستخدم نفس جلسة اللوحة.
الأخطاء ترجع JSON دائماً، حتى لو الطلب ما فيه `Accept: application/json`.

شكل الردود هو شكل Laravel الافتراضي:

- عنصر واحد: `{ "data": { ... } }` (Eloquent API Resources).
- قائمة مع ترقيم: `{ "data": [...], "links": {...}, "meta": {...} }`.
- خطأ تحقق (422): `{ "message": "...", "errors": { "field": ["..."] } }`.

من JavaScript استخدم `App.api` بدل `fetch` مباشرة:

```js
const { data } = await App.api.get('status');            // GET /dashboard/api/v1/status
await App.api.get('courses', { page: 2 });                // ?page=2
try {
  await App.api.post('courses', { title });
} catch (e) {
  if (e.status === 422) e.errors.title?.[0];              // أخطاء الحقول، الصفحة تعرضها بنفسها
}
```

### النقاط الموجودة

المصادقة من **Laravel Fortify** (`config/fortify.php`) تحت نفس الأساس `/dashboard/api/v1/auth`.
الصفحات نفسها (الدخول، كلمة مرور جديدة) هي واجهاتنا في `routes/web.php`، لأن `fortify.views` مطفأ.

| الطريقة | المسار | المصدر | الوظيفة |
|---|---|---|---|
| POST | `auth/login` | Fortify | تسجيل الدخول (`email`, `password`, `remember`) — 5 محاولات فاشلة بالدقيقة |
| POST | `auth/logout` | Fortify | تسجيل الخروج |
| POST | `auth/forgot-password` | Fortify | إرسال رابط الاستعادة (نفس الرد سواء البريد موجود أو لا) |
| POST | `auth/reset-password` | Fortify | تعيين كلمة مرور من رابط الاستعادة (صالح ساعة) |
| PUT | `auth/user/password` | Fortify | تغيير كلمة المرور (`current_password`) وتسجيل الخروج من باقي الأجهزة |
| POST | `invitations/accept` | `AcceptedInvitationController` | قبول دعوة وتعيين أول كلمة مرور (صالحة 7 أيام، جدول `invitation_tokens`) |
| PUT | `profile` | `ProfileController` | تعديل بياناتي |
| POST | `profile/avatar` | `AvatarController` | رفع الصورة الشخصية (JPG/PNG/WebP حتى 3MB) |
| GET | `team` | `TeamMemberController` | أعضاء الفريق + الصلاحيات اللي بقدر أعطيها |
| POST | `team` | `TeamMemberController` | دعوة عضو (بيوصله إيميل) |
| PATCH | `team/{id}` | `TeamMemberController` | تغيير صلاحية عضو |
| DELETE | `team/{id}` | `TeamMemberController` | إزالة عضو وتسجيل خروجه من كل أجهزته |
| GET | `sessions` | `SessionController` | أجهزتي المتصلة |
| DELETE | `sessions/{id}` | `SessionController` | إنهاء جهاز آخر |
| GET | `notifications` | `NotificationController` | آخر 20 إشعار لي + عدد غير المقروء (`unread_count`) |
| POST | `notifications/{id}/read` · `notifications/read` | `NotificationReadController` | تعليم إشعار كمقروء · تعليم الكل |
| PUT | `notification-preferences` | `NotificationPreferenceController` | أي تنبيهات بتوصلني بالإيميل كمان (`{"orders": true, "messages": false}`) |
| GET | `dashboard` | `DashboardController` | كل أرقام الرئيسية (شوف تحت) |
| GET | `analytics?days=` | `AnalyticsController` | تحليلات المبيعات والطلاب لآخر 7 / 30 / 90 يوم أو 12 شهر (`365`) |
| GET | `activity` | `ActivityController` | سجل النشاط، 20 بالصفحة (`meta.next_cursor` ← `?cursor=`)، و`?limit=6` للرئيسية |
| GET | `search?q=` | `SearchController` | البحث العام: لحد 5 نتائج من كل نوع، كل نتيجة معها `page` و`params` لـ `App.url` |

#### المحتوى (القراءة لكل الأعضاء، الكتابة لمن يملك `manage-content`)

| الطريقة | المسار | الوظيفة |
|---|---|---|
| GET · POST | `courses` | قائمة الدورات · إنشاء دورة مع منهجها (`modules[].lessons[]`) |
| GET · PUT · DELETE | `courses/{id}` | الدورة كاملة مع المنهج · حفظ النموذج كله · حذف |
| PUT | `courses/{id}/status` | نشر / إخفاء / مراجعة من القائمة (لا تُنشر دورة بلا دروس) |
| POST | `courses/{id}/copies` | نسخ الدورة ومنهجها كمسودة |
| POST | `courses/{id}/cover` | صورة الغلاف (حتى 5MB) |
| GET · POST | `workshops` | الورش (الحالة: مفتوحة/مكتملة/منتهية تُحسب تلقائياً) |
| PUT · DELETE | `workshops/{id}` | تعديل · حذف |
| GET · POST | `articles` | قائمة المقالات (بدون النص) · إنشاء |
| GET · PUT · DELETE | `articles/{id}` | المقال كاملاً · حفظ · حذف |
| POST | `articles/{id}/cover` | صورة المقال |
| GET · POST | `tools` | الأدوات بترتيبها · إضافة في آخر القائمة |
| PATCH · DELETE | `tools/{id}` | تعديل أي حقل (مثلاً `is_published` فقط) · حذف |
| POST | `tools/{id}/move` | تقديم/تأخير (`direction: up/down`) |
| GET · POST | `tool-categories` | التصنيفات مع عدد أدواتها · إضافة |
| PATCH · DELETE | `tool-categories/{id}` | إعادة تسمية/لون · حذف (`?move_to=` إذا فيه أدوات) |
| POST | `tool-categories/{id}/move` | تقديم/تأخير |

في المنهج، الوحدات والدروس اللي معها `id` بتتحدث مكانها (عشان تقدّم الطلاب بالمرحلة 3 يضل مربوط فيها)، واللي بدون `id` بتنضاف، واللي مش موجودة بالقائمة بتنحذف.
الحالات والمستويات والتصنيفات بتنخزن بالإنجليزي (`draft`, `published`…) والـ API بيرجّع معها `*_label` بالعربي.
الرابط (slug) بيتولد من الكلمات الإنجليزية في العنوان، وإذا العنوان كله عربي بيصير `course-xxxxxx` / `article-xxxxxx`. رابط المقال ثابت بعد إنشائه.
عدد طلاب الدورة = اشتراكاتها، وإيراداتها = مجموع طلباتها المكتملة، ومقاعد الورشة المحجوزة = طلباتها المكتملة. الدورة اللي فيها طلاب والورشة اللي فيها مسجلين ما بينحذفوا. تقييم الدورة = متوسط تقييماتها المنشورة (0 لو ما في).

#### الطلاب والمبيعات

| الطريقة | المسار | الصلاحية | الوظيفة |
|---|---|---|---|
| GET | `students` | الكل | الطلاب مع دوراتهم ونسبة الإنجاز والمدفوع والحالة (نشط / غير نشط بعد 30 يوم بلا نشاط / موقوف) |
| GET | `students/{id}` | الكل | ملف الطالب: دوراته مع الإنجاز وآخر 5 طلبات |
| PUT | `students/status` | `manage-students` | إيقاف أو تفعيل طالب أو أكثر (`ids`, `status: active/suspended`) |
| POST | `students/messages` | `manage-students` | إيميل لطالب أو أكثر (`{الاسم}` بيتبدّل باسم كل طالب، والموقوفين بيتخطّوا) |
| GET | `orders` | الكل | الطلبات |
| POST | `orders/{id}/payment` | `manage-sales` | تأكيد دفع طلب معلّق (مثلاً تحويل بنكي وصل) |
| POST | `orders/{id}/refund` | `manage-sales` | تسجيل استرداد وسحب الوصول |
| POST | `orders/{id}/failure` | `manage-sales` | إغلاق طلب معلّق كفاشل (ويرجع استخدام الكوبون) |
| POST | `orders/{id}/invoice` | `manage-sales` | إرسال الفاتورة للطالب |
| GET · POST | `coupons` | الكل · `manage-sales` | الكوبونات · إنشاء (`usage_limit: 0` = بلا حد) |
| PATCH · DELETE | `coupons/{id}` | `manage-sales` | تشغيل/إيقاف (`is_active`) · حذف (الطلبات بتحتفظ بالكود) |
| GET | `workshops/{id}/registrations` | الكل | المسجلون في الورشة |
| POST | `workshops/{id}/reminders` | `manage-content` | إيميل تذكير لكل المسجلين |

#### الرسائل والتقييمات وطلبات المشاريع

| الطريقة | المسار | الصلاحية | الوظيفة |
|---|---|---|---|
| GET | `conversations` | الكل | المحادثات (الأحدث نشاطاً أولاً) مع آخر رسالة وحالة القراءة |
| GET | `conversations/{id}` | الكل | المحادثة مع كل رسائلها |
| POST | `conversations` | `answer-messages` | فتح محادثة مع عميل (`lead_id`) أو طالب (`student_id`)؛ لو موجودة بترجع نفسها |
| POST · DELETE | `conversations/{id}/read` | `answer-messages` | تعليم كمقروءة (الصفحة بتعملها لما تفتح المحادثة) · كغير مقروءة |
| POST | `conversations/{id}/messages` | `answer-messages` | رد (`body` و/أو ملف `attachment` لحد 10MB)؛ بينبعت للعميل بالإيميل مع المرفق |
| GET | `conversations/{id}/messages/{id}/attachment` | الكل | تنزيل المرفق باسمه الأصلي |
| DELETE | `conversations/{id}` | `answer-messages` | حذف المحادثة مع رسائلها وملفاتها |
| GET | `reviews` | الكل | التقييمات مع الطالب والدورة والرد |
| PUT | `reviews/{id}/status` | `moderate-reviews` | `published` (نشر) أو `hidden` (رفض/إخفاء) |
| PUT · DELETE | `reviews/{id}/reply` | `moderate-reviews` | كتابة/تعديل الرد العام (بيتسجل مين رد) · حذفه |
| DELETE | `reviews/{id}` | `moderate-reviews` | حذف نهائي (للسبام) |
| GET · POST | `leads` | الكل · `manage-leads` | طلبات المشاريع · إضافة (بتبدأ بمرحلة "جديد") |
| PATCH · DELETE | `leads/{id}` | `manage-leads` | نقل لمرحلة (`stage`) أو تعديل أي حقل · حذف (محادثته بتضل) |

المرفقات محفوظة على القرص الخاص `local` (`storage/app/private/conversations`) ومش متاحة إلا للأعضاء عبر الـ API.
الرسائل الواردة من الطلاب والعملاء رح توصل لما نبني الموقع العام (نموذج التواصل)؛ هلّأ موجودة من الـ seeder بس.
عدّادات القائمة الجانبية (رسائل غير مقروءة، تقييمات بانتظار المراجعة، طلبات جديدة) بتيجي من `app/Support/NavCounts.php` مع كل صفحة.

#### الإشعارات وسجل النشاط والبحث

**الجرس** (`app/Notifications/Alerts`): كل حدث بيوصل للأدوار اللي بتشتغل عليه، ما عدا العضو اللي عمله، والدعوات المعلّقة ما بيوصلها إشي:

| التنبيه | متى | لمين | إيميل افتراضياً |
|---|---|---|---|
| `OrderPaid` | طلب صار مكتمل | `manage-sales` | ✓ |
| `LeadReceived` | طلب مشروع جديد | `manage-leads` | ✓ |
| `ReviewSubmitted` | تقييم جديد بانتظار المراجعة | `moderate-reviews` | ✓ |
| `ContactMessageReceived` | رسالة من طالب أو عميل (وبترجّع المحادثة غير مقروءة) | `answer-messages` | — |

الـ Observers في `app/Observers` بتطلقهم من أي مكان صار فيه الحدث (اللوحة، أو بعدين الموقع العام وبوابة الدفع).
الإشعار بيوصل الجرس فوراً، والإيميل بيستنى الـ queue. كل عضو بيختار الإيميلات من الإعدادات ← الإشعارات (`users.notification_preferences`).
الجرس بيتحدّث كل دقيقة والصفحة مفتوحة.

**سجل النشاط** (`app/Models/Concerns/LogsActivity.php`): أي إضافة أو تعديل أو حذف بيعمله عضو مسجّل دخول على الدورات، الورش، المقالات، الأدوات وتصنيفاتها، الكوبونات، الطلاب، الطلبات، طلبات المشاريع، التقييمات، المحادثات والفريق.
تغيير الحالة (منشور، مكتمل، موقوف، مرحلة الطلب، الصلاحية…) بيتسجّل كـ "غيّر حالة … إلى …". التغييرات بدون عضو (الـ scheduler، الـ seeders، الموقع العام) ما بتتسجّل.
بيظهر في "آخر النشاطات" بالرئيسية وبتبويب "سجل النشاط" بالإعدادات. لإضافة نوع جديد: `use LogsActivity` وعرّف `activityLabel()`.

**الرئيسية والتحليلات** (`app/Support/DashboardSummary.php` و `app/Support/SalesReport.php`):
- **الإيرادات** = اللي دفعه العملاء بالطلبات المكتملة حسب يوم الدفع (المستردة بتطلع منها)، و"خدمات التطوير" = ميزانية طلبات المشاريع المقبولة حسب يوم قبولها (`leads.decided_at`، بيتعبّى لحاله لما المرحلة تصير مقبول أو مرفوض).
- مؤشرات الرئيسية بتقارن الشهر الحالي لليوم بنفس الأيام من الشهر الماضي، ومعها آخر 12 شهر للرسم الصغير. "معدل إكمال الدورات" = متوسط تقدّم كل الاشتراكات.
- التحليلات بتقارن الفترة بالفترة اللي قبلها، يومياً لحد 90 يوم وشهرياً للسنة، ومعها رحلة الطلاب الجدد (سجّلوا ← بدأوا طلب ← دفعوا ← اشتروا أكثر من مرة)، طرق الدفع، الأكثر مبيعاً، ودول المشترين.
- الأرقام المجمّعة محفوظة بالـ Cache لـ 5 دقائق (`dashboard.summary` و `analytics.{days}`)؛ أحدث الطلبات والورش وطلبات المشاريع دايماً مباشرة.
- بيانات الزيارات (المصادر، الأجهزة، الصفحات) بدها أداة إحصاءات للموقع العام مثل Plausible أو Google Analytics.

#### الإعدادات والبيانات

| الطريقة | المسار | الصلاحية | الوظيفة |
|---|---|---|---|
| GET | `settings` | الكل | كل الإعدادات؛ السرية بترجع `{set, hint}` بس، و`meta.gateways` حالة كل بوابة |
| PUT | `settings` | `manage-settings` | حفظ أي مجموعة إعدادات (المفتاح السري الفاضي بيضل زي ما هو) |
| DELETE | `settings/secrets/{key}` | `manage-platform-data` | حذف مفتاح سري محفوظ |
| POST | `settings/test-email` | `manage-platform-data` | رسالة تجريبية فورية لبريدك |
| GET | `site-settings` | بدون دخول | اللي بيحتاجه الموقع العام: الاسم، التواصل، الصيانة، التسجيل، العملة، الضريبة، وطرق الدفع الجاهزة مع مفاتيحها العامة |
| GET · POST | `data-exports` | `manage-platform-data` | النسخ الجاهزة · تجهيز نسخة بالخلفية (بيوصلك إشعار) |
| GET · DELETE | `data-exports/{file}` | `manage-platform-data` | تنزيل · حذف نسخة |
| POST | `data-wipe` | `manage-platform-data` | حذف كل البيانات (`password` + `confirmation: "احذف كل البيانات"`) |

**الإعدادات** (`app/Support/PlatformSettings.php`): كل مفتاح معه قيمته الافتراضية وقواعد التحقق، ومحفوظة بجدول `settings` (مفتاح/قيمة).
- المفاتيح السرية (Stripe، PayPal، كلمة مرور SMTP) مشفّرة بمفتاح التطبيق (`APP_KEY`)، وما بترجع للمتصفح أبداً، وحتى الـ Cache بيحفظها مشفّرة. لو تغيّر `APP_KEY` بتصير المفاتيح القديمة "غير محفوظة" (بدل ما يوقع الموقع) وبتنكتب من جديد.
- بوابات الدفع وإعدادات البريد (المفاتيح المعلّمة `owner` بالتعريفات) للمالك بس: هي اللي بتحدد وين بتروح الفلوس والإيميلات. وتغيير خادم SMTP أو اسم المستخدم بيمسح كلمة المرور المحفوظة.
- بتنطبق مع كل طلب ومع كل Job: اسم المنصة (`app.name`)، مدة الجلسة، سعر Pro، وخادم SMTP وعنوان المرسل (لو الخادم فاضي بتضل إعدادات `.env`).
- العملة بتظهر بكل المبالغ باللوحة وبالإيميلات، والضريبة "ضمن السعر" بتنكتب بالفاتورة مع ملاحظة الفاتورة، وتعليمات التحويل البنكي بتنبعت مع فاتورة الطلب المعلّق.
- التقرير الأسبوعي، وإرسال المقالات الجديدة للطلاب (لما يكون خيار "إرساله في النشرة" بالمقال مفعّل كمان)، وتنبيه الدخول من جهاز جديد (`app/Listeners/CheckLoginDevice.php`) كلهم بيقرأوا من الإعدادات.
- **مسح البيانات** بيحذف الدورات والورش والمقالات والأدوات والطلاب والطلبات والكوبونات والتقييمات والرسائل وطلبات المشاريع مع ملفاتها، وبيضل الفريق والإعدادات وسجل النشاط. **التصدير** ZIP فيه ملف JSON لكل جدول بدون كلمات المرور والمفاتيح السرية.
- لسا مش مبني: ربط الدفع الفعلي ببوابات Stripe وPayPal (بيصير بالموقع العام وبينادي `CompleteOrder`)، والتحقق بخطوتين (مكتوب "قريباً").

**البحث العام**: بالشريط العلوي (`/` للتركيز، الأسهم وEnter للتنقل). النتائج بتفتح الصفحة المناسبة: الطالب والطلب والأداة بـ `?q=`، الدورة والمقال على صفحة التعديل، طلب المشروع بـ `?lead=`، والمحادثة بـ `?c=`.

**الأمان**:
- روابط الإيميلات (استعادة كلمة المرور، الدعوات، التنبيهات) مبنية على `APP_URL` (`app/Support/AppUrl.php`) وما بتعتمد على `Host` الطلب، وبالإنتاج `trustHosts()` بيرفض أي host غير `APP_URL`. **لازم `APP_URL` يكون الرابط الصحيح.**
- استعادة كلمة المرور بتطلّع العضو من كل الأجهزة، وتغيير البريد بالملف الشخصي بيطلب كلمة المرور الحالية وبيبلّغ البريد القديم.
- كل رابط عليه حد طلبات (`throttle`) إله عدّاده الخاص (`throttle:N,M,اسم`)، و`App.api` ما بيبعت نفس طلب الكتابة مرتين وهو لسا شغّال (ضغطتين ورا بعض).
- تصدير CSV بيحط `'` قبل أي خلية بتبدأ بـ `= + - @` عشان ما تشتغل كمعادلة بـ Excel.

**دورة حياة الطلب** (`app/Actions/Orders`): `PlaceOrder` بينشئ طلب معلّق (بيتحقق من المنتج والكوبون وبيحجز استخدام الكوبون بعملية وحدة ذرّية، فحد الاستخدام ما بينكسر حتى لو طلبين بنفس اللحظة)
← `CompleteOrder` (دفع: دورة = اشتراك، ورشة = مقعد، Pro = 30 يوم إضافي؛ بيرفض تأكيد طلب لورشة مقاعدها خلصت أو لدورة الطالب مشترك فيها من طلب ثاني) ← `RefundOrder` (بيسحب الوصول، واستخدام الكوبون ما بيرجع)، أو `FailOrder` (بيرجّع استخدام الكوبون).
الطلب بيحتفظ باسم المنتج وسعره وقت الشراء. لما نربط بوابة دفع، الـ webhook تبعها بينادي نفس الـ Actions، ولحد هداك الوقت الاسترداد الفعلي للمال بيصير من البوابة نفسها.
سعر شهر Pro من الإعدادات ← الدفع (القيمة الافتراضية من `PRO_MONTH_PRICE` في `config/sales.php`).

كل روابط Fortify عليها كمان حد 20 طلب بالدقيقة لكل IP (`throttle:fortify`).
تخصيص Fortify في `app/Providers/FortifyServiceProvider.php` و `app/Actions/Fortify` و `app/Http/Responses`.
التسجيل العام مطفأ (الأعضاء بيدخلوا بدعوة بس)، والتحقق بخطوتين جاهز للتفعيل من `features` في `config/fortify.php` لما نبني شاشاته.

### الأدوار

| الدور | إدارة الفريق | المحتوى | الطلاب (إيقاف، مراسلة) | المبيعات (دفع، استرداد، كوبونات) | الرسائل (رد) | التقييمات (مراجعة) | طلبات المشاريع | الإعدادات | تصدير ومسح البيانات |
|---|---|---|---|---|---|---|---|---|---|
| مالك (`owner`) | الكل ما عدا نفسه، وهو الوحيد اللي بيعيّن مدراء | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| مدير (`admin`) | محرري المحتوى والدعم الفني والمحاسبين فقط | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ ما عدا بوابات الدفع والبريد | — |
| محرر محتوى (`editor`) | — | ✓ | — | — | — | ✓ | — | — | — |
| دعم فني (`support`) | — | — | ✓ | — | ✓ | ✓ | — | — | — |
| محاسب (`accountant`) | — | — | — | ✓ | — | — | — | — | — |

كل الأعضاء بيقدروا يشوفوا كل الصفحات؛ الجدول بيحدد مين بيعدّل.

في الواجهة، أي عنصر عليه `data-requires="manage_content"` (أو `manage_students`، `manage_sales`، `answer_messages`، `moderate_reviews`، `manage_leads`، `manage_settings`، `manage_platform_data`) بيختفي لمن لا يملك الصلاحية، و `App.can('…')` لنفس الغرض بالـ JavaScript. السيرفر بيرفض الكتابة (403) في كل الأحوال.

القواعد في `app/Policies/UserPolicy.php` و `app/Enums/Role.php`. صلاحيات باقي الصفحات بتنضاف مع كل مرحلة.

`App.api` بيبعت توكن CSRF من `<meta name="csrf-token">`، وبيعرض إشعار خطأ بالعربي لكل الحالات ما عدا 422، وبيحوّل على صفحة الدخول عند 401.
لرفع الملفات أرسل `FormData` مع `post` (PHP لا يقرأ ملفات `PUT`).

## إضافة صفحة جديدة

1. أنشئ `resources/views/<entity>/index.blade.php` يرث `layouts.dashboard` (انسخ أقرب واجهة).
2. أضف الرابط في `routes/web.php`، وأضف اسمه في `layouts/partials/app-config.blade.php`.
3. أضف عنصراً في مصفوفة `NAV` داخل `app.js`: `{ page: '<entity>', href: url('<entity>'), icon: '...', label: '...' }`.
4. اكتب منطقها في `public/assets/dashboard/js/pages/<entity>.js` داخل `document.addEventListener('app:ready', ...)`.

## التجاوب

- **أكبر من 1024px**: قائمة جانبية ثابتة قابلة للطي.
- **أقل من 1024px**: قائمة منزلقة.
- **أقل من 760px**: عمود واحد، والمؤشرات في عمودين، والجداول تُمرَّر أفقياً.
