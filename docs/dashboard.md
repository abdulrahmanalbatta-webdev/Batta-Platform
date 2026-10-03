# لوحة تحكم Batta داخل Laravel

الواجهات صارت Blade في `resources/views` ومقسمة حسب الكيان. التنسيقات والسكربتات والصور في `public/assets/dashboard`.
مربوط بقاعدة البيانات عبر الـ API: الدخول، الملف الشخصي، الفريق، الأجهزة المتصلة، الدورات (مع المنهج)، الورش، المقالات، الأدوات وتصنيفاتها، الطلاب، الطلبات والكوبونات.
باقي الصفحات (الرسائل، التقييمات، طلبات المشاريع، الرئيسية، التحليلات) ما زالت تقرأ بيانات تجريبية من `public/assets/dashboard/js/data.js`.

## التشغيل

يحتاج PHP 8.4 أو أحدث.

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

- **الإيميلات** (رسائل الطلاب، الفواتير، تذكير الورش) بتنبعت عن طريق الـ queue (`QUEUE_CONNECTION=database`)، فلازم يكون في worker شغال:
  محلياً `composer run dev` (بيشغّل السيرفر والـ queue والـ logs مع بعض) أو `php artisan queue:work` بنافذة لحاله، وعلى الخادم `queue:work` تحت Supervisor أو ما يشبهه.
- **المقالات المجدولة** بتنشر بأمر `articles:publish-scheduled` كل دقيقة، وهذا يحتاج الـ scheduler:
  محلياً `php artisan schedule:work`، وعلى الخادم سطر cron واحد يشغّل `php artisan schedule:run` كل دقيقة.

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
│   ├── data.js          بيانات تجريبية (window.DB)
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
عدد طلاب الدورة = اشتراكاتها، وإيراداتها = مجموع طلباتها المكتملة، ومقاعد الورشة المحجوزة = طلباتها المكتملة. الدورة اللي فيها طلاب والورشة اللي فيها مسجلين ما بينحذفوا. التقييمات لسا صفر (المرحلة 4).

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

**دورة حياة الطلب** (`app/Actions/Orders`): `PlaceOrder` بينشئ طلب معلّق (بيتحقق من المنتج والكوبون وبيحجز استخدام الكوبون بعملية وحدة ذرّية، فحد الاستخدام ما بينكسر حتى لو طلبين بنفس اللحظة)
← `CompleteOrder` (دفع: دورة = اشتراك، ورشة = مقعد، Pro = شهر إضافي) ← `RefundOrder` (بيسحب الوصول، واستخدام الكوبون ما بيرجع)، أو `FailOrder` (بيرجّع استخدام الكوبون).
الطلب بيحتفظ باسم المنتج وسعره وقت الشراء. لما نربط بوابة دفع، الـ webhook تبعها بينادي نفس الـ Actions، ولحد هداك الوقت الاسترداد الفعلي للمال بيصير من البوابة نفسها.
سعر شهر Pro في `config/sales.php` (`PRO_MONTH_PRICE`).

كل روابط Fortify عليها كمان حد 20 طلب بالدقيقة لكل IP (`throttle:fortify`).
تخصيص Fortify في `app/Providers/FortifyServiceProvider.php` و `app/Actions/Fortify` و `app/Http/Responses`.
التسجيل العام مطفأ (الأعضاء بيدخلوا بدعوة بس)، والتحقق بخطوتين جاهز للتفعيل من `features` في `config/fortify.php` لما نبني شاشاته.

### الأدوار

| الدور | إدارة الفريق | المحتوى | الطلاب (إيقاف، مراسلة) | المبيعات (دفع، استرداد، كوبونات) |
|---|---|---|---|---|
| مالك (`owner`) | الكل ما عدا نفسه، وهو الوحيد اللي بيعيّن مدراء | ✓ | ✓ | ✓ |
| مدير (`admin`) | محرري المحتوى والدعم الفني والمحاسبين فقط | ✓ | ✓ | ✓ |
| محرر محتوى (`editor`) | — | ✓ | — | — |
| دعم فني (`support`) | — | — | ✓ | — |
| محاسب (`accountant`) | — | — | — | ✓ |

كل الأعضاء بيقدروا يشوفوا كل الصفحات؛ الجدول بيحدد مين بيعدّل.

في الواجهة، أي عنصر عليه `data-requires="manage_content"` (أو `manage_students`، `manage_sales`) بيختفي لمن لا يملك الصلاحية، و `App.can('…')` لنفس الغرض بالـ JavaScript. السيرفر بيرفض الكتابة (403) في كل الأحوال.

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
