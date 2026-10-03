# لوحة تحكم Batta داخل Laravel

الواجهات صارت Blade في `resources/views` ومقسمة حسب الكيان. التنسيقات والسكربتات والصور في `public/assets/dashboard`.
البيانات ما زالت تجريبية في `public/assets/dashboard/js/data.js`، ولم تُربط بقاعدة البيانات بعد.

## التشغيل

```bash
php artisan serve
```

ثم افتح `http://localhost:8000/dashboard`، أو `http://batta-dashboard.test/dashboard` عبر Herd.

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
| `/login` | `login` | `auth.login` |

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

## إضافة صفحة جديدة

1. أنشئ `resources/views/<entity>/index.blade.php` يرث `layouts.dashboard` (انسخ أقرب واجهة).
2. أضف الرابط في `routes/web.php`، وأضف اسمه في `layouts/partials/app-config.blade.php`.
3. أضف عنصراً في مصفوفة `NAV` داخل `app.js`: `{ page: '<entity>', href: url('<entity>'), icon: '...', label: '...' }`.
4. اكتب منطقها في `public/assets/dashboard/js/pages/<entity>.js` داخل `document.addEventListener('app:ready', ...)`.

## التجاوب

- **أكبر من 1024px**: قائمة جانبية ثابتة قابلة للطي.
- **أقل من 1024px**: قائمة منزلقة.
- **أقل من 760px**: عمود واحد، والمؤشرات في عمودين، والجداول تُمرَّر أفقياً.
