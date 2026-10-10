# لوحة تحكم Batta داخل Laravel

الواجهات صارت Blade في `resources/views` ومقسمة حسب الكيان. التنسيقات والسكربتات والصور في `public/assets/dashboard`.
مربوط بقاعدة البيانات عبر الـ API: الدخول، الملف الشخصي، الفريق، الأجهزة المتصلة، الدورات (إعلانات بدون دروس)، الورش، المقالات، الأدوات وتصنيفاتها، الطلاب، الطلبات والكوبونات، الرسائل، التقييمات، التعليقات (على المقالات والدورات والورش مع ردود الطلاب)، طلبات المشاريع، جرس الإشعارات، سجل النشاط، والبحث العام.
والرئيسية والتحليلات بأرقام حقيقية من التسجيلات والطلاب. ما ضل في بيانات تجريبية (`data.js` انحذف)، والإعدادات (عام، البريد، الإشعارات، الأمان) محفوظة بقاعدة البيانات ومطبّقة.

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

## النشر على خادم (Production)

**`.env` على الخادم** (غير الموجود بـ `.env.example`):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dash.example.com      # روابط الإيميلات بتنبني عليه، والطلبات بـ host ثاني بترجع 400
LOG_LEVEL=warning
DB_CONNECTION=mysql                   # مع DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true            # الجلسة بس على HTTPS
QUEUE_CONNECTION=database
CACHE_STORE=database
CORS_ALLOWED_ORIGINS=https://batta.dev,https://www.batta.dev   # دومين الموقع العام (Vue) اللي بينادي /api/v1
```

**أول مرة:**

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate              # مرة وحدة بس: تغييره بيخلي المفاتيح السرية المحفوظة "غير محفوظة"
php artisan migrate --force
php artisan storage:link
php artisan app:create-owner admin@example.com "الاسم"
php artisan optimize                  # config + routes + views cache
```

**مع كل تحديث:** `git pull && composer install --no-dev -o && php artisan migrate --force && php artisan optimize && php artisan queue:restart`

**عمليتين لازم يضلوا شغّالين:**
- **الـ queue** (الإيميلات، التصدير، التقرير الأسبوعي) تحت Supervisor:
  ```ini
  [program:batta-queue]
  command=php /var/www/batta/artisan queue:work --sleep=3 --tries=3 --max-time=3600
  autostart=true
  autorestart=true
  user=www-data
  ```
- **الـ scheduler** بسطر cron واحد: `* * * * * cd /var/www/batta && php artisan schedule:run >> /dev/null 2>&1`

**الخادم (nginx)**: الـ root على `public/`، وHTTPS (Let's Encrypt)، وكل الطلبات لـ `index.php`. الـ `server_name` لازم يطابق `APP_URL`.

**الأمان المفعّل تلقائياً بالإنتاج**: Content-Security-Policy بـ nonce (`config/security.php`؛ طافي بالبيئة المحلية بس)، و`X-Frame-Options`/`nosniff`/`Referrer-Policy`، وHSTS على HTTPS، وحد 300 طلب بالدقيقة لكل عضو على الـ API (فوق الحدود الخاصة ببعض الروابط)، و`trustHosts`.

**النسخ الاحتياطي**: `app:backup-database` كل ليلة الساعة 3 (`mysqldump` بلقطة وحدة متسقة، مضغوط) بـ `storage/app/private/backups`، وبيحذف الأقدم من 14 يوم. **انسخهم برّا الخادم** (rclone/S3 أو نسخ مزوّد الاستضافة)، لأن نسخة على نفس الخادم ما بتحمي من خرابه. نسخة البيانات من الإعدادات (ZIP/JSON) للنقل والأرشيف، مش بديل عن هاد.

### على Laravel Cloud

ملفات الخادم على Cloud بتنمسح مع كل نشر وما بتنشارك بين النسخ، فالملفات بتروح على تخزين Cloud (R2) عن طريق `league/flysystem-aws-s3-v3`:
- **bucket عام** اسمه `public` (الأغلفة، الشعارات، الصور الشخصية، صور محتوى الموقع)، والكود بيستخدم `Storage::disk('public')`.
- **bucket خاص** هو الـ disk الافتراضي: مرفقات الرسائل (`ConversationMessage::attachmentDisk()`) ونسخ البيانات (`ExportPlatformData::disk()`). ملف الـ ZIP بيتبني بملف مؤقت وبعدين بيترفع.

متغيرات البيئة هناك (غير اللي بتنحقن تلقائياً لقاعدة البيانات والتخزين):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dash.example.com
TRUSTED_PROXIES=*          # الطلبات بتوصل من load balancer
DB_BACKUPS=false           # Cloud بيعمل نسخ قاعدة البيانات، وما في mysqldump
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database
CORS_ALLOWED_ORIGINS=https://example.com
```

- أمر البناء: `composer install --no-dev --optimize-autoloader && php artisan optimize`، وأمر النشر: `php artisan migrate --force`.
- فعّل الـ scheduler على الـ cluster، وشغّل `php artisan queue:work --tries=3 --max-time=3600` كـ background process (أو worker cluster).
- بعد أول نشر: `php artisan app:create-owner EMAIL "الاسم" --generate-password` عن طريق `cloud command:run` (أو من لوحة Cloud). ما في terminal تكتب فيه كلمة السر، فبيطبع كلمة سر مؤقتة، غيّرها من الملف الشخصي أول ما تدخل.
- أداة سطر الأوامر موجودة كـ dev dependency: `./vendor/bin/cloud` (التوكن بمتغير `LARAVEL_CLOUD_TOKEN`).

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
├── leads/     index               طلبات المشاريع
├── students/  index               الطلاب
├── messages/  index               الرسائل
├── reviews/   index               التقييمات
├── comments/  index               التعليقات على المقالات والدورات والورش
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
| `/dashboard/paths` · `/create` · `/{id}/edit` | `paths.index` · `paths.create` · `paths.edit` | `paths.index` · `paths.form` |
| `/dashboard/articles` · `/create` · `/{id}/edit` | `articles.index` · `articles.create` · `articles.edit` | `articles.index` · `articles.form` |
| `/dashboard/tools` | `tools.index` | `tools.index` |
| `/dashboard/leads` | `leads.index` | `leads.index` |
| `/dashboard/students` | `students.index` | `students.index` |
| `/dashboard/messages` | `messages.index` | `messages.index` |
| `/dashboard/reviews` | `reviews.index` | `reviews.index` |
| `/dashboard/comments` | `comments.index` | `comments.index` |
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
| PUT | `notification-preferences` | `NotificationPreferenceController` | أي تنبيهات بتوصلني بالإيميل كمان (`{"registrations": true, "messages": false}`) |
| GET | `dashboard` | `DashboardController` | كل أرقام الرئيسية (شوف تحت) |
| GET | `analytics?days=` | `AnalyticsController` | تحليلات التسجيلات والطلاب لآخر 7 / 30 / 90 يوم أو 12 شهر (`365`) |
| GET | `analytics/traffic?days=` | `TrafficController` | زيارات الموقع من Google Analytics لنفس الفترات. دايماً 200: `meta.configured` و`meta.error` بيقولوا ليش ما في بيانات |
| GET | `activity` | `ActivityController` | سجل النشاط، 20 بالصفحة (`meta.next_cursor` ← `?cursor=`)، و`?limit=6` للرئيسية |
| GET | `search?q=` | `SearchController` | البحث العام: لحد 5 نتائج من كل نوع، كل نتيجة معها `page` و`params` لـ `App.url` |

#### المحتوى (القراءة لكل الأعضاء، الكتابة لمن يملك `manage-content`)

| الطريقة | المسار | الوظيفة |
|---|---|---|
| GET · POST | `courses` | قائمة الدورات · إنشاء دورة |
| GET · PUT · DELETE | `courses/{id}` | الدورة كاملة · حفظ النموذج كله · حذف |
| PUT | `courses/{id}/status` | نشر / إخفاء / مراجعة من القائمة (لا تُنشر دورة بلا دروس) |
| POST | `courses/{id}/copies` | نسخ الدورة كمسودة |
| POST | `courses/{id}/cover` | صورة الغلاف (حتى 5MB) |
| GET · POST | `workshops` | الورش (الحالة: مفتوحة/مكتملة/منتهية تُحسب تلقائياً) |
| PUT · DELETE | `workshops/{id}` | تعديل · حذف |
| GET · POST | `articles` | قائمة المقالات (بدون النص) · إنشاء |
| GET · PUT · DELETE | `articles/{id}` | المقال كاملاً · حفظ · حذف |
| POST | `articles/{id}/cover` | صورة المقال |
| GET · POST | `tools` | الأدوات بترتيبها · إضافة في آخر القائمة |
| PATCH · DELETE | `tools/{id}` | تعديل أي حقل (مثلاً `is_published` فقط) · حذف |
| POST | `tools/{id}/move` | تقديم/تأخير (`direction: up/down`) |
| POST · DELETE | `tools/{id}/logo` | رفع شعار الأداة (`logo`: JPG/PNG/WebP لحد 2MB، بيستبدل القديم) · إزالته. بدون شعار الموقع بيعرض الرمز على لون الأداة |
| GET · POST | `tool-categories` | التصنيفات مع عدد أدواتها · إضافة |
| PATCH · DELETE | `tool-categories/{id}` | إعادة تسمية/لون · حذف (`?move_to=` إذا فيه أدوات) |
| POST | `tool-categories/{id}/move` | تقديم/تأخير |
| GET · POST | `learning-paths` | المسارات بترتيبها مع عدد المراحل والمصادر والمحتوى المرتبط · إضافة في آخر القائمة |
| GET · PUT · PATCH · DELETE | `learning-paths/{id}` | المسار كامل بمراحله · حفظ المحرّر كله · `is_published` بس (زر الإظهار بالقائمة) · حذف |
| POST | `learning-paths/{id}/move` | تقديم/تأخير (`direction: up/down`) |

الدورات إعلانات بدون دروس: ما في منهج ولا تقدّم، والطالب بيسجّل فيها من الموقع.
الحالات والمستويات والتصنيفات بتنخزن بالإنجليزي (`draft`, `published`…) والـ API بيرجّع معها `*_label` بالعربي.
الرابط (slug) بيتولد من الكلمات الإنجليزية في العنوان، وإذا العنوان كله عربي بيصير `course-xxxxxx` / `article-xxxxxx`. رابط المقال ثابت بعد إنشائه.
**المسارات** (`/dashboard/paths`، جدول `learning_paths`): خريطة تعلّم لكل تخصص. المسار إله اسم ورابط وأيقونة ووصف و"لمن" ومدة ومخرجات، ومراحل بالترتيب (لحد 15) محفوظة بعمود `stages` (JSON). بكل مرحلة: عنوان ووصف ومواضيع، مصادر مجانية (لحد 12: اسم، رابط http/https، نوع `video`/`course`/`docs`/`article`/`book`/`practice`، لغة `ar`/`en`)، ومحتوى من المنصة (لحد 10: `{type: course|workshop|article, id}`). الربط لازم يكون لمحتوى موجود، والموقع بيعرض منه بس الدورات المنشورة والورش الجاية والمقالات المنشورة، فالمخفي بيضل مربوط وبيرجع يظهر لما ينتشر. المسار المخفي (`is_published = false`) بيضل باللوحة وما بيظهر بالموقع. المسارات كانت قبل بصفحة "محتوى الموقع"، والـ migration نقلتها للجدول (أو المسارات الثلاثة الأصلية من `resources/data/learning-paths.json` إذا ما انعدّلت).
التسجيل مجاني (ما في أسعار ولا دفع). عدد طلاب الدورة = المسجّلين فيها، ومقاعد الورشة المحجوزة = تسجيلاتها (`workshop_registrations`). الدورة اللي فيها طلاب والورشة اللي فيها مسجلين ما بينحذفوا. تقييم الدورة = متوسط تقييماتها المنشورة (0 لو ما في).

#### الطلاب والتسجيلات

| الطريقة | المسار | الصلاحية | الوظيفة |
|---|---|---|---|
| GET | `students` | الكل | الطلاب مع عدد دوراتهم وورشهم والحالة (نشط / غير نشط بعد 30 يوم بلا نشاط / موقوف) |
| GET | `students/{id}` | الكل | ملف الطالب: الدورات والورش المسجّل فيها مع تاريخ التسجيل |
| PUT | `students/status` | `manage-students` | إيقاف أو تفعيل طالب أو أكثر (`ids`, `status: active/suspended`) |
| POST | `students/messages` | `manage-students` | إيميل لطالب أو أكثر (`{الاسم}` بيتبدّل باسم كل طالب، والموقوفين بيتخطّوا) |
| GET | `workshops/{id}/registrations` | الكل | المسجلون في الورشة بترتيب تسجيلهم |
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
| GET | `comments` | الكل | كل التعليقات مع الطالب ومكانها (`type`: article/course/workshop، `target`، `target_key` لرابط الموقع)، والتعليق اللي بترد عليه (`parent`) ورد الفريق. حذف تعليق بيحذف الردود عليه |
| PUT · PUT · DELETE · DELETE | `comments/{id}/status` · `comments/{id}/reply` · `comments/{id}/reply` · `comments/{id}` | `moderate-reviews` | نفس التقييمات: نشر أو إخفاء، كتابة الرد أو حذفه، حذف نهائي |
| GET · POST | `leads` | الكل · `manage-leads` | طلبات المشاريع · إضافة (بتبدأ بمرحلة "جديد") |
| PATCH · DELETE | `leads/{id}` | `manage-leads` | نقل لمرحلة (`stage`) أو تعديل أي حقل · حذف (محادثته بتضل) |

المرفقات محفوظة على القرص الخاص `local` (`storage/app/private/conversations`) ومش متاحة إلا للأعضاء عبر الـ API.
الرسائل الواردة من الطلاب والعملاء رح توصل لما نبني الموقع العام (نموذج التواصل)؛ هلّأ موجودة من الـ seeder بس.
عدّادات القائمة الجانبية (رسائل غير مقروءة، تقييمات بانتظار المراجعة، طلبات جديدة) بتيجي من `app/Support/NavCounts.php` مع كل صفحة.

#### الإشعارات وسجل النشاط والبحث

**الجرس** (`app/Notifications/Alerts`): كل حدث بيوصل للأدوار اللي بتشتغل عليه، ما عدا العضو اللي عمله، والدعوات المعلّقة ما بيوصلها إشي:

| التنبيه | متى | لمين | إيميل افتراضياً |
|---|---|---|---|
| `RegistrationReceived` | طالب سجّل في دورة أو ورشة من الموقع | `manage-students` | ✓ |
| `LeadReceived` | طلب مشروع جديد | `manage-leads` | ✓ |
| `ReviewSubmitted` | تقييم جديد بانتظار المراجعة | `moderate-reviews` | ✓ |
| `CommentSubmitted` | تعليق أو رد جديد (مقال، دورة، ورشة) بانتظار المراجعة | `moderate-reviews` | ✓ |
| `ContactMessageReceived` | رسالة من طالب أو عميل (وبترجّع المحادثة غير مقروءة) | `answer-messages` | — |

الـ Observers في `app/Observers` بتطلقهم من أي مكان صار فيه الحدث (اللوحة أو الموقع العام).
الإشعار بيوصل الجرس فوراً، والإيميل بيستنى الـ queue. كل عضو بيختار الإيميلات من الإعدادات ← الإشعارات (`users.notification_preferences`).
الجرس بيتحدّث كل دقيقة والصفحة مفتوحة.

**سجل النشاط** (`app/Models/Concerns/LogsActivity.php`): أي إضافة أو تعديل أو حذف بيعمله عضو مسجّل دخول على الدورات، الورش، المقالات، الأدوات وتصنيفاتها، الطلاب، طلبات المشاريع، التقييمات، المحادثات والفريق.
تغيير الحالة (منشور، مكتمل، موقوف، مرحلة الطلب، الصلاحية…) بيتسجّل كـ "غيّر حالة … إلى …". التغييرات بدون عضو (الـ scheduler، الـ seeders، الموقع العام) ما بتتسجّل.
بيظهر في "آخر النشاطات" بالرئيسية وبتبويب "سجل النشاط" بالإعدادات. لإضافة نوع جديد: `use LogsActivity` وعرّف `activityLabel()`.

**الرئيسية والتحليلات** (`app/Support/DashboardSummary.php` و `app/Support/RegistrationsReport.php`):
- **التسجيلات** = تسجيلات الدورات (`enrollments`) ومقاعد الورش (`workshop_registrations`) حسب يوم التسجيل. الرئيسية فيها كمان الطلاب الجدد وطلبات المشاريع، وقيمة طلبات المشاريع المفتوحة والمقبولة.
- مؤشرات الرئيسية بتقارن الشهر الحالي لليوم بنفس الأيام من الشهر الماضي، ومعها آخر 12 شهر للرسم الصغير.
- التحليلات بتقارن الفترة بالفترة اللي قبلها، يومياً لحد 90 يوم وشهرياً للسنة، ومعها رحلة الطلاب الجدد (حسابات جديدة ← سجّلوا في دورة أو ورشة ← سجّلوا بأكثر من وحدة)، نسبة الدورات للورش، الأكثر تسجيلاً، ودول المسجّلين.
- الأرقام المجمّعة محفوظة بالـ Cache لـ 5 دقائق (`dashboard.summary` و `analytics.{days}`)؛ أحدث التسجيلات والورش وطلبات المشاريع دايماً مباشرة.
- زيارات الموقع (الزوار، الجلسات، المشاهدات، معدل التفاعل، المصادر، الأجهزة، أكثر الصفحات) من Google Analytics 4، محفوظة بالـ Cache ساعة. شوف "ربط Google Analytics" تحت.

#### الإعدادات والبيانات

| الطريقة | المسار | الصلاحية | الوظيفة |
|---|---|---|---|
| GET | `settings` | الكل | كل الإعدادات؛ السرية بترجع `{set, hint}` بس، و`meta.gateways` حالة كل بوابة |
| PUT | `settings` | `manage-settings` | حفظ أي مجموعة إعدادات (المفتاح السري الفاضي بيضل زي ما هو) |
| DELETE | `settings/secrets/{key}` | `manage-platform-data` | حذف مفتاح سري محفوظ |
| POST | `settings/test-email` | `manage-platform-data` | رسالة تجريبية فورية لبريدك |
| POST | `settings/analytics-test` | `manage-settings` | بيجرّب رقم الموقع ومفتاح حساب الخدمة المحفوظين، والخطأ (422) بيشرح السبب |
| GET · POST | `data-exports` | `manage-platform-data` | النسخ الجاهزة · تجهيز نسخة بالخلفية (بيوصلك إشعار) |
| GET · DELETE | `data-exports/{file}` | `manage-platform-data` | تنزيل · حذف نسخة |
| POST | `data-wipe` | `manage-platform-data` | حذف كل البيانات (`password` + `confirmation: "احذف كل البيانات"`) |

**محتوى الموقع** (`/dashboard/site-content`، `app/Support/SiteContent.php`): كل نص ثابت بالموقع العام صار قسم بيتعدّل من هون (شريط الإعلان، الكلمات المتبدّلة، شريط الأدوات بصفحة "من أنا"، "لماذا تعمل معي"، التعريف والقصة والمسيرة والقيم والحسابات، الخدمات والباقات ومراحل العمل، آراء العملاء والأسئلة الشائعة، ونصوص الصفحات: عناوين الأقسام ومقدّماتها بكل صفحة، خيارات الميزانية بنموذج عرض السعر، التذييل، رسالة الصيانة، صفحتي الدخول والتسجيل، وأسماء الصفحات (بالعناوين والقائمة والتذييل) ونصوص الأزرار ورسائل الصفحات الفارغة؛ وعنوان الموقع ووصفه بمحركات البحث والمشاركة، اللي بيتثبّتوا بالموقع وقت `npm run build`)، وصورتك الشخصية (صفحة "من أنا" وبجانب اسمك بالمقالات والردود؛ بدونها بيظهر أول حرف من اسمك). صورة البطاقة المعلّقة بالرئيسية إلها حقل خاص تحت التعريف (`profile.cutout`، PNG شفافة بدون خلفية)، بترفعها من المحرّر عبر `POST site-images` وبتتخزّن بـ `site/images`، وبتنحذف لما تشيلها أو تبدّلها. كل قسم إله schema (الحقول والحدود) بيرسم المحرّر وبيتحقق من القيم، وبينحفظ بجدول `site_blocks`. القسم اللي ما انعدّل بيرجع لنص الموقع الأصلي (`resources/data/site-content.json`)، و"استعادة الأصل" بترجّعه. ولو انضاف حقل جديد لقسم محفوظ من قبل، الموقع بياخذ نصه الأصلي لحد ما تعدّله. API: `GET site-content` (الكل)، و`PUT`/`DELETE site-content/{key}` و`POST`/`DELETE site-photo` (`manage-content`)، وكل حفظ بينسجّل بسجل النشاط.

**الإعدادات** (`app/Support/PlatformSettings.php`): كل مفتاح معه قيمته الافتراضية وقواعد التحقق، ومحفوظة بجدول `settings` (مفتاح/قيمة).
- المفاتيح السرية (كلمة مرور SMTP، ومفتاح حساب خدمة Google Analytics) مشفّرة بمفتاح التطبيق (`APP_KEY`)، وما بترجع للمتصفح أبداً، وحتى الـ Cache بيحفظها مشفّرة. لو تغيّر `APP_KEY` بتصير المفاتيح القديمة "غير محفوظة" (بدل ما يوقع الموقع) وبتنكتب من جديد.
- إعدادات البريد ومفتاح Google (المفاتيح المعلّمة `owner` بالتعريفات) للمالك بس: هي اللي بتحدد وين بتروح الإيميلات. وتغيير خادم SMTP أو اسم المستخدم بيمسح كلمة المرور المحفوظة.
- بتنطبق مع كل طلب ومع كل Job: اسم المنصة (`app.name`)، مدة الجلسة، وخادم SMTP وعنوان المرسل (لو الخادم فاضي بتضل إعدادات `.env`).
- العملة (بتبويب عام) بتظهر مع ميزانيات طلبات المشاريع باللوحة وبالإيميلات.
- التقرير الأسبوعي وتنبيه الدخول من جهاز جديد (`app/Listeners/CheckLoginDevice.php`) بيقرأوا من الإعدادات.
- **مسح البيانات** بيحذف الدورات والورش والمقالات والأدوات والطلاب والتسجيلات والتقييمات والرسائل وطلبات المشاريع مع ملفاتها، وبيضل الفريق والإعدادات وسجل النشاط. **التصدير** ZIP فيه ملف JSON لكل جدول بدون كلمات المرور والمفاتيح السرية.
- **التسجيل مجاني**: الطالب بيسجّل بالدورة أو بيحجز مقعد بالورشة بضغطة من الموقع (وبيقدر يلغي)، والفريق بيوصله تنبيه "تسجيل جديد" (جرس وإيميل لمن يدير الطلاب) فيه اسمه ورقمه للتواصل.
- لسا مش مبني: التحقق بخطوتين (مكتوب "قريباً").

**البحث العام**: بالشريط العلوي (`/` للتركيز، الأسهم وEnter للتنقل). النتائج بتفتح الصفحة المناسبة: الطالب والأداة بـ `?q=`، الدورة والمقال على صفحة التعديل، طلب المشروع بـ `?lead=`، والمحادثة بـ `?c=`.

**الأمان**:
- روابط الإيميلات (استعادة كلمة المرور، الدعوات، التنبيهات) مبنية على `APP_URL` (`app/Support/AppUrl.php`) وما بتعتمد على `Host` الطلب، وبالإنتاج `trustHosts()` بيرفض أي host غير `APP_URL`. **لازم `APP_URL` يكون الرابط الصحيح.**
- استعادة كلمة المرور بتطلّع العضو من كل الأجهزة، وتغيير البريد بالملف الشخصي بيطلب كلمة المرور الحالية وبيبلّغ البريد القديم.
- كل رابط عليه حد طلبات (`throttle`) إله عدّاده الخاص (`throttle:N,M,اسم`)، و`App.api` ما بيبعت نفس طلب الكتابة مرتين وهو لسا شغّال (ضغطتين ورا بعض).
- تصدير CSV بيحط `'` قبل أي خلية بتبدأ بـ `= + - @` عشان ما تشتغل كمعادلة بـ Excel.

كل روابط Fortify عليها كمان حد 20 طلب بالدقيقة لكل IP (`throttle:fortify`).
تخصيص Fortify في `app/Providers/FortifyServiceProvider.php` و `app/Actions/Fortify` و `app/Http/Responses`.
التسجيل العام مطفأ (الأعضاء بيدخلوا بدعوة بس)، والتحقق بخطوتين جاهز للتفعيل من `features` في `config/fortify.php` لما نبني شاشاته.

### ربط Google Analytics

**على الموقع العام** (تتبّع الزيارات): `GET /api/v1/settings` بيرجع `ga_measurement_id` (مثل `G-XXXXXXXXXX`). لو موجود، الموقع (Vue) بيحمّل gtag وبيبعت مشاهدة مع كل تنقّل:

```js
const { ga_measurement_id: id } = settings;
if (id) {
  const s = document.createElement('script');
  s.async = true;
  s.src = `https://www.googletagmanager.com/gtag/js?id=${id}`;
  document.head.append(s);
  window.dataLayer = window.dataLayer || [];
  window.gtag = function () { dataLayer.push(arguments); };
  gtag('js', new Date());
  gtag('config', id, { send_page_view: false });
  router.afterEach((to) => gtag('event', 'page_view', { page_path: to.fullPath, page_title: document.title }));
}
```

**باللوحة** (قراءة التقارير بصفحة التحليلات): الإعدادات ← الإحصاءات:
1. **رقم الموقع (Property ID)**: من Google Analytics ← الإدارة ← تفاصيل الموقع (رقم، مش `G-…`).
2. **مفتاح حساب الخدمة (للمالك بس)**: بـ Google Cloud Console فعّل *Google Analytics Data API*، واعمل Service account، ونزّل مفتاح JSON والصقه. بينحفظ مشفّر وما بيرجع للمتصفح، والتلميح بيعرض بريد الحساب بس.
3. بـ Google Analytics ← إدارة الوصول إلى الموقع، ضيف بريد حساب الخدمة (`…@….iam.gserviceaccount.com`) بدور **Viewer**.
4. "اختبار الاتصال" بيأكد إنه شغّال، أو بيقول شو الناقص (صلاحية، رقم غلط، مفتاح ملغي).

`app/Support/GoogleAnalytics.php` بيحكي مع Google مباشرة بدون SDK: حساب الخدمة بيوقّع JWT (RS256) بياخد مقابله access token (محفوظ بالـ Cache لحد قبل ما يخلص بشوي)، وبعدين طلب `batchRunReports` واحد بيجيب كل التقرير. الخادم لازم يقدر يوصل لـ `oauth2.googleapis.com` و`analyticsdata.googleapis.com`.

### الأدوار

| الدور | إدارة الفريق | المحتوى | الطلاب (إيقاف، مراسلة) | الرسائل (رد) | التقييمات (مراجعة) | طلبات المشاريع | الإعدادات | تصدير ومسح البيانات |
|---|---|---|---|---|---|---|---|---|
| مالك (`owner`) | الكل ما عدا نفسه، وهو الوحيد اللي بيعيّن مدراء | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| مدير (`admin`) | محرري المحتوى والدعم الفني فقط | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ ما عدا البريد | — |
| محرر محتوى (`editor`) | — | ✓ | — | — | ✓ | — | — | — |
| دعم فني (`support`) | — | — | ✓ | ✓ | ✓ | — | — | — |

دور "محاسب" انشال مع الدفع، والأعضاء اللي كانوا محاسبين صاروا دعم فني.

كل الأعضاء بيقدروا يشوفوا كل الصفحات؛ الجدول بيحدد مين بيعدّل.

في الواجهة، أي عنصر عليه `data-requires="manage_content"` (أو `manage_students`، `answer_messages`، `moderate_reviews`، `manage_leads`، `manage_settings`، `manage_platform_data`) بيختفي لمن لا يملك الصلاحية، و `App.can('…')` لنفس الغرض بالـ JavaScript. السيرفر بيرفض الكتابة (403) في كل الأحوال.

القواعد في `app/Policies/UserPolicy.php` و `app/Enums/Role.php`. صلاحيات باقي الصفحات بتنضاف مع كل مرحلة.

`App.api` بيبعت توكن CSRF من `<meta name="csrf-token">`، وبيعرض إشعار خطأ بالعربي لكل الحالات ما عدا 422، وبيحوّل على صفحة الدخول عند 401.
لرفع الملفات أرسل `FormData` مع `post` (PHP لا يقرأ ملفات `PUT`).

## API الموقع العام (routes/site-api.php)

الموقع العام (مستودع `batta-website`، Vue) بيحكي مع اللوحة عبر `/api/v1` (مش `/dashboard/api/v1`). هاد الـ API **بدون جلسة ولا CSRF**: الطالب بيسجّل دخول وبياخد token (Laravel Sanctum) وبيبعته بكل طلب بـ `Authorization: Bearer <token>`. الطلبات من دومين ثاني مسموحة بس من `CORS_ALLOWED_ORIGINS` (`config/cors.php`). الـ token صالح 30 يوم (`config/sanctum.php`)، وبيبلش بـ `batta_` عشان أدوات فحص الأسرار تلقطه لو انتشر.

الأخطاء JSON زي اللوحة: 401 بدون token صالح، 403 بدون صلاحية، 404، 422 مع `errors`، و429 للحدود. الحدود: 120 طلب بالدقيقة لكل عنوان IP، و5 محاولات دخول بالدقيقة لكل بريد+IP، و5 بالدقيقة لكل نموذج (تواصل، طلب مشروع، تسجيل، نسيت كلمة المرور، تعيينها).

#### بدون دخول

| الطريقة | المسار | الوظيفة |
|---|---|---|
| GET | `settings` | الاسم، التواصل، الصيانة، `registration_open`، التعليقات، العملة (`currency_symbol`)، `ga_measurement_id` لـ Google Analytics، و`project_services` لنموذج طلب المشروع (نفس خدمات صفحة "محتوى الموقع"، المعرّف ← الاسم؛ الطلبات القديمة بتحتفظ بخدمتها لو انحذفت). ولا مفتاح سري |
| GET | `content` | محتوى الموقع من صفحة "محتوى الموقع" باللوحة: الإعلان، الرئيسية، "من أنا"، الخدمات والباقات ومراحل العمل، الآراء والأسئلة، نصوص الصفحات (`texts_home`، `texts_pages`، `texts_learning`، `texts_general`، `texts_ui`)، و`seo`، وصورتك (`photo`، أو null) |
| GET | `stats` | أرقام حقيقية لشريط الإحصائيات بالموقع: الطلاب، الدورات المنشورة، المشاريع المنجزة (عملاء بمرحلة "تم")، المقالات المنشورة، وعدد التقييمات المنشورة ومتوسطها. محفوظة بالـ Cache 10 دقائق |
| GET | `courses` · `courses/{slug}` | الدورات المنشورة (بدون الحالة) · صفحة الدورة مع وصفها |
| GET | `courses/{slug}/reviews` | التقييمات المنشورة، 20 بالصفحة، بالاسم الأول بس |
| GET | `workshops` | الورش من اليوم وطالع مع `seats_left` |
| GET | `articles` · `articles/{slug}` | المقالات المنشورة، 12 بالصفحة (`?per_page=` لحد 50، `?category=`، `?featured=1`) · المقال كامل، وكل قراءة بتزيد المشاهدات |
| GET | `articles/{slug}/comments` · `courses/{slug}/comments` · `workshops/{id}/comments` | التعليقات المنشورة، الأقدم أولاً، 50 بالصفحة، بالاسم الأول بس، مع رد الفريق وردود الطلاب المنشورة (`replies`) |
| GET | `workshops/{id}` | صفحة الورشة (حتى لو انتهت، عشان تضل تعليقاتها) |
| GET | `paths` · `paths/{slug}` | المسارات المنشورة بترتيب اللوحة (مع عناوين مراحلها وعدد المصادر) · خريطة المسار كاملة: بكل مرحلة نصها ومواضيعها ومصادرها، و`courses` و`workshops` و`articles` المرتبطة بنفس شكل قوائمها (المنشور والجاي بس) |
| GET | `tools` | الأدوات المنشورة مجمّعة حسب التصنيف |
| POST | `tools/{id}/click` | بيعدّ نقرة على رابط الأداة (عمود النقرات باللوحة) |
| POST | `contact` | `name`, `email`, `message` (+ `topic`: `course`/`workshop`/`account`/`suggestion`/`other`، و`about` اسم الدورة أو الورشة) ← بتوصل للرسائل باللوحة وبتنبّه الفريق، والموضوع بيظهر فوق الرسالة. لو معه token بتنضاف لمحادثة الطالب نفسه |
| POST | `project-requests` | `name`, `email`, `service`, `details` (+ `company`, `phone`, `budget`) ← عميل جديد بمرحلة "جديد" وتنبيه |
| POST | `auth/register` | `name`, `email`, `phone` (واتساب مع مقدّمة الدولة)، `password` + `password_confirmation` (+ `country`, `device_name`). بينبّه الفريق ← `{token, data}`. بيرجع 403 لو التسجيل مسكّر من الإعدادات |
| POST | `auth/login` | `email`, `password` (+ `device_name`) ← `{token, data}`. الحساب الموقوف 403 |
| POST | `auth/forgot-password` | بيبعت رابط لـ `site_url/reset-password?token=…&email=…`. نفس الرد سواء البريد موجود أو لا |
| POST | `auth/reset-password` | `token`, `email`, `password` + `password_confirmation`، وبيطلّع الطالب من كل أجهزته |

النموذجين (`contact` و`project-requests`) فيهم حقل مخفي `website`: الموقع لازم يخليه فاضي ومخفي، والبوتات اللي بتعبّيه بتاخد نفس الرد بس ما بينحفظ إشي.

الطالب اللي ضافه الفريق قبل ما يكون عنده حساب ما إله كلمة مرور: التسجيل بنفس البريد بيرفض، وبيعيّنها من "نسيت كلمة المرور".

#### للطالب المسجّل (Bearer token)

| الطريقة | المسار | الوظيفة |
|---|---|---|
| POST | `auth/logout` | بيلغي الـ token الحالي |
| GET · PUT | `me` | حسابه · تعديل `name`, `email`, `phone`, `country` (تغيير البريد بدو `current_password`) |
| PUT | `me/password` | `current_password`, `password` + `password_confirmation`، وبيطلّعه من باقي الأجهزة |
| POST | `articles/{slug}/comments` · `courses/{slug}/comments` · `workshops/{id}/comments` | `body`، و`parent_id` اختياري للرد على تعليق منشور بنفس الصفحة (مستوى واحد) ← تعليق على مقال أو دورة منشورة أو ورشة، بيستنى المراجعة بصفحة "التعليقات" باللوحة وبينبّه الفريق. لو التعليقات مسكّرة من الإعدادات 403 |
| GET | `me/courses` | الدورات اللي سجّل فيها، الأحدث أولاً |
| POST · DELETE | `me/courses/{slug}` | تسجيل مجاني بدورة منشورة (201 أول مرة مع تنبيه للفريق، 200 لو مسجّل) · إلغاء التسجيل (204) |
| GET | `me/workshops` | الورش اللي حجز فيها مقعد (القادمة والمنتهية)، الأقرب أولاً |
| POST · DELETE | `me/workshops/{id}` | حجز مقعد (422 `workshop` لو انتهت أو المقاعد خلصت؛ العدّ والحجز بنفس الـ transaction مع قفل الورشة) · إرجاع المقعد قبل موعدها (204) |
| GET | `me/courses/{slug}` | الدورة مع تقييمه (`my_review`). لو مش مسجّل 403 |
| PUT | `me/courses/{slug}/review` | `rating` (1-5) و`body` ← تقييم جديد أو تعديله، وبيرجع "بانتظار المراجعة" بكل تعديل |

**الوصول لدورة** (`Student::canAccess`): مسجّل فيها. إيقاف الطالب من اللوحة بيلغي كل الـ tokens تبعته فوراً.

ما في شراء ولا دفع: التسجيل مجاني من صفحة الدورة أو الورشة.

## إضافة صفحة جديدة

1. أنشئ `resources/views/<entity>/index.blade.php` يرث `layouts.dashboard` (انسخ أقرب واجهة).
2. أضف الرابط في `routes/web.php`، وأضف اسمه في `layouts/partials/app-config.blade.php`.
3. أضف عنصراً في مصفوفة `NAV` داخل `app.js`: `{ page: '<entity>', href: url('<entity>'), icon: '...', label: '...' }`.
4. اكتب منطقها في `public/assets/dashboard/js/pages/<entity>.js` داخل `document.addEventListener('app:ready', ...)`.

## التجاوب

- **أكبر من 1024px**: قائمة جانبية ثابتة قابلة للطي.
- **أقل من 1024px**: قائمة منزلقة.
- **أقل من 760px**: عمود واحد، والمؤشرات في عمودين، والجداول تُمرَّر أفقياً.
