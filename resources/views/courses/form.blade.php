@extends('layouts.dashboard')

@section('title', 'دورة جديدة')
@section('page', 'course-form')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><a href="{{ route('courses.index') }}">الدورات</a><span class="sep">/</span><span id="crumb">دورة جديدة</span></div>
        <h1 id="pageTitle">دورة جديدة</h1>
        <p>املأ بيانات الدورة، ثم أضف الوحدات والدروس.</p>
      </div>
      <div class="page-actions">
        <a class="btn btn-ghost" href="{{ route('courses.index') }}">إلغاء</a>
        <button class="btn btn-ghost" type="button" data-save="draft"><i data-icon="save" class="sm"></i>حفظ كمسودة</button>
        <button class="btn btn-primary" type="button" data-save="publish"><i data-icon="check" class="sm"></i>نشر الدورة</button>
      </div>
    </div>

    <form class="grid g-side" id="courseForm" novalidate>
      <div style="display:flex;flex-direction:column;gap:20px;min-width:0">
        <div class="card">
          <div class="card-head"><h3>المعلومات الأساسية</h3></div>
          <div class="card-body form-grid">
            <div class="field full">
              <label for="title">عنوان الدورة *</label>
              <input class="input" id="title" placeholder="مثلاً: Next.js من الصفر إلى الإنتاج" required>
              <span class="error" id="titleError" hidden>عنوان الدورة مطلوب</span>
            </div>
            <div class="field full">
              <label for="slug">رابط الدورة</label>
              <div class="input-group"><span class="addon ltr">{{ parse_url($siteUrl, PHP_URL_HOST) }}/courses/</span><input class="input ltr" id="slug" placeholder="nextjs-production" style="text-align:left"></div>
            </div>
            <div class="field full">
              <label for="short">وصف قصير</label>
              <input class="input" id="short" maxlength="140" placeholder="جملة واحدة تظهر في بطاقة الدورة">
              <span class="hint"><span id="shortCount">0</span>/140 حرفاً</span>
            </div>
            <div class="field full">
              <label for="desc">وصف الدورة</label>
              <textarea class="textarea" id="desc" rows="5" placeholder="ماذا سيتعلم الطالب؟ ولمن هذه الدورة؟"></textarea>
            </div>
            <div class="field full">
              <label>ماذا سيتعلم الطالب؟</label>
              <div class="tags-input" id="outcomes"><input placeholder="اكتب نقطة واضغط Enter" aria-label="مخرجات التعلم"></div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-head">
            <div><h3>المنهج</h3><p id="curriculumSummary">0 وحدات · 0 دروس</p></div>
            <button type="button" class="btn btn-soft btn-sm" id="addModule"><i data-icon="plus" class="sm"></i>وحدة جديدة</button>
          </div>
          <div class="card-body" id="modules"></div>
        </div>

        <div class="card">
          <div class="card-head"><h3>التسعير</h3></div>
          <div class="card-body form-grid">
            <div class="field">
              <label for="price">السعر</label>
              <div class="input-group"><input class="input ltr" id="price" type="number" min="0" placeholder="0" style="text-align:left"><span class="addon">{{ trim($currencySymbol) }}</span></div>
              <span class="hint">اترك 0 لدورة مجانية</span>
            </div>
            <div class="field">
              <label for="oldPrice">السعر قبل الخصم</label>
              <div class="input-group"><input class="input ltr" id="oldPrice" type="number" min="0" placeholder="اختياري" style="text-align:left"><span class="addon">{{ trim($currencySymbol) }}</span></div>
            </div>
            <div class="field full">
              <label class="switch"><input type="checkbox" id="ppp" checked><span class="track"></span>تسعير إقليمي تلقائي (أسعار أقل للدول ذات الدخل المنخفض)</label>
            </div>
            <div class="field full">
              <label class="switch"><input type="checkbox" id="pro" checked><span class="track"></span>متاحة لمشتركي Pro بدون دفع إضافي</label>
            </div>
          </div>
        </div>
      </div>

      <aside style="display:flex;flex-direction:column;gap:20px;min-width:0">
        <div class="card">
          <div class="card-head"><h3>النشر</h3></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
            <div class="field">
              <label for="status">الحالة</label>
              <select class="select" id="status"><option value="draft">مسودة</option><option value="review">قيد المراجعة</option><option value="published">منشورة</option></select>
            </div>
            <div class="field">
              <label for="publishAt">موعد النشر</label>
              <input class="input" id="publishAt" type="date">
            </div>
            <label class="switch"><input type="checkbox" id="certificate" checked><span class="track"></span>شهادة إتمام</label>
            <label class="switch"><input type="checkbox" id="comments" checked><span class="track"></span>السماح بالأسئلة على الدروس</label>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><h3>صورة الغلاف</h3></div>
          <div class="card-body">
            <label class="dropzone" id="cover">
              <input type="file" accept="image/*" aria-label="رفع صورة الغلاف">
              <i data-icon="image"></i>
              <b>اسحب الصورة هنا أو اضغط للرفع</b>
              <small>PNG أو JPG · 1280×720 بكسل</small>
            </label>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><h3>التصنيف</h3></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
            <div class="field">
              <label for="level">المستوى</label>
              <select class="select" id="level"><option value="beginner">مبتدئ</option><option value="intermediate">متوسط</option><option value="advanced">متقدم</option></select>
            </div>
            <div class="field">
              <label for="category">القسم</label>
              <select class="select" id="category"><option value="frontend">تطوير الواجهات</option><option value="backend">تطوير الخلفية</option><option value="full-stack">Full-stack</option><option value="freelancing">العمل الحر</option></select>
            </div>
            <div class="field">
              <label>الوسوم</label>
              <div class="tags-input" id="tags"><input placeholder="أضف وسماً" aria-label="الوسوم"></div>
            </div>
          </div>
        </div>
      </aside>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/course-form.js') }}"></script>
@endpush
