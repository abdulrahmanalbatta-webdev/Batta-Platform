---
title: تعلّم Laravel للمبتدئين: أول مشروع لك خطوة بخطوة
slug: laravel-first-project
category: tutorials
excerpt: دليل عملي لبناء أول مشروع لك بـ Laravel: التثبيت، المسارات، المتحكمات، قاعدة البيانات، وعرض البيانات في صفحة. نبني معاً تطبيق ملاحظات بسيط من الصفر.
meta_title: تعلّم Laravel للمبتدئين: أول مشروع خطوة بخطوة
meta_description: ابنِ أول مشروع لك بـ Laravel من الصفر: التثبيت، المسارات، المتحكمات، النماذج وقاعدة البيانات، وعرض البيانات في صفحة Blade.
---

Laravel إطار عمل للغة PHP يجعل بناء مواقع وتطبيقات الويب أسرع وأكثر تنظيماً، لأنه يعطيك جاهزاً أغلب ما تحتاجه: المسارات، والتعامل مع قاعدة البيانات، وتسجيل الدخول، والحماية. لتبدأ تحتاج PHP وComposer، ثم أمراً واحداً لإنشاء المشروع. في هذا المقال نبني معاً تطبيق ملاحظات صغيراً يحفظ الملاحظات في قاعدة بيانات ويعرضها في صفحة.

## ماذا تحتاج قبل أن تبدأ؟

- أساسيات PHP: المتغيرات، الدوال، المصفوفات، والكائنات.
- أساسيات HTML.
- PHP وComposer مثبتان على جهازك.

أسهل طريقة لتجهيز كل شيء دفعة واحدة هي Laravel Herd على ويندوز وماك، أو Laragon على ويندوز. كلاهما يثبّت PHP وComposer وقاعدة بيانات بضغطة.

## الخطوة 1: إنشاء المشروع

افتح الطرفية (Terminal) واكتب:

```bash
composer create-project laravel/laravel notes-app
cd notes-app
php artisan serve
```

افتح المتصفح على `http://127.0.0.1:8000` وسترى صفحة الترحيب. مبروك، مشروعك يعمل.

> الأمر `php artisan` هو مساعدك في Laravel. جرّب `php artisan list` لترى كل ما يستطيع فعله.

## الخطوة 2: فهم بنية المشروع

لن تحتاج كل المجلدات الآن. ركّز على هذه:

- `routes/web.php`: هنا تعرّف روابط موقعك.
- `app/Http/Controllers`: المتحكمات، وهي التي تستقبل الطلب وتقرر ماذا تفعل.
- `app/Models`: النماذج، وكل نموذج يمثّل جدولاً في قاعدة البيانات.
- `database/migrations`: ملفات تبني جداول قاعدة البيانات.
- `resources/views`: صفحات الواجهة بصيغة Blade.

## الخطوة 3: قاعدة البيانات والنموذج

أنشئ نموذج الملاحظة مع ملف بناء الجدول بأمر واحد:

```bash
php artisan make:model Note -m
```

افتح ملف الـ migration الجديد داخل `database/migrations` وأضف حقلي العنوان والمحتوى:

```php
Schema::create('notes', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('body');
    $table->timestamps();
});
```

ثم ابنِ الجدول:

```bash
php artisan migrate
```

المشروع الجديد يستخدم SQLite افتراضياً، فلا تحتاج إعداد قاعدة بيانات منفصلة للتجربة.

في النموذج `app/Models/Note.php` حدّد الحقول المسموح بتعبئتها:

```php
class Note extends Model
{
    protected $fillable = ['title', 'body'];
}
```

## الخطوة 4: المتحكم والمسارات

أنشئ متحكماً:

```bash
php artisan make:controller NoteController
```

واكتب فيه دالتين: واحدة تعرض الملاحظات، وأخرى تحفظ ملاحظة جديدة:

```php
class NoteController extends Controller
{
    public function index()
    {
        return view('notes', ['notes' => Note::latest()->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'max:100'],
            'body' => ['required'],
        ]);

        Note::create($data);

        return redirect('/notes');
    }
}
```

لاحظ `validate`: Laravel يتحقق من البيانات ويرجع المستخدم مع رسائل الخطأ تلقائياً إن كانت ناقصة.

ثم اربط الروابط في `routes/web.php`:

```php
Route::get('/notes', [NoteController::class, 'index']);
Route::post('/notes', [NoteController::class, 'store']);
```

## الخطوة 5: الصفحة

أنشئ الملف `resources/views/notes.blade.php`:

```blade
<h1>ملاحظاتي</h1>

<form method="POST" action="/notes">
    @csrf
    <input name="title" placeholder="العنوان">
    <textarea name="body" placeholder="الملاحظة"></textarea>
    <button>حفظ</button>
</form>

@foreach ($notes as $note)
    <h2>{{ $note->title }}</h2>
    <p>{{ $note->body }}</p>
@endforeach
```

السطر `@csrf` يحمي النموذج من هجمات تزوير الطلبات، وLaravel يرفض النموذج بدونه. و`{{ }}` تعرض القيمة بعد تنظيفها من أي كود ضار تلقائياً.

افتح `http://127.0.0.1:8000/notes`، أضف ملاحظة، وستراها تظهر في القائمة.

## ماذا تعلّمت في هذا المشروع؟

- إنشاء مشروع Laravel وتشغيله.
- بناء جدول بالـ migrations والتعامل معه عبر نموذج (Eloquent).
- استقبال الطلبات في متحكم والتحقق من البيانات.
- عرض البيانات في صفحة Blade بأمان.

هذه هي الدورة الأساسية لأي تطبيق ويب: مسار، ثم متحكم، ثم نموذج وقاعدة بيانات، ثم صفحة. كل التطبيقات الكبيرة مبنية على هذا النمط نفسه.

## طوّر المشروع بنفسك

جرّب إضافة هذه الميزات لتثبيت ما تعلّمته:

- حذف ملاحظة وتعديلها.
- صفحة لعرض ملاحظة واحدة.
- تسجيل دخول بحيث يرى كل مستخدم ملاحظاته فقط، وستجد في حزم Laravel الرسمية ما يجهّز لك هذا بسرعة.

## أسئلة شائعة

**هل Laravel مناسب للمبتدئين؟**
نعم، بشرط أن تعرف أساسيات PHP أولاً. توثيقه من أوضح التوثيقات، ومجتمعه كبير، وأغلب مشاكلك ستجد حلها بسرعة.

**هل Laravel مطلوب في سوق العمل؟**
هو من أكثر أطر PHP استخداماً، ويُستخدم في مواقع الشركات والمتاجر ولوحات التحكم والأنظمة الداخلية، وهو خيار ممتاز للعمل الحر لأنه يختصر وقت التطوير كثيراً.

## الخطوة التالية

هذا المشروع بداية. إن أردت أن تتعلّم Laravel بشكل منظم حتى تبني تطبيقات كاملة بلوحات تحكم وAPIs وتنشرها على الإنترنت، تابع مسار الخوادم (Backend) في الموقع.
