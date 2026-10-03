@extends('layouts.dashboard')

@section('title', 'محرر المقالات')
@section('page', 'article-editor')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><a href="{{ route('articles.index') }}">المقالات</a><span class="sep">/</span><span id="crumb">مقال جديد</span></div>
        <h1 id="pageTitle">مقال جديد</h1>
        <p id="saveState" class="muted">لم يُحفظ بعد</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-ghost" id="previewBtn"><i data-icon="eye" class="sm"></i>معاينة</button>
        <button class="btn btn-ghost" data-save="draft"><i data-icon="save" class="sm"></i>حفظ المسودة</button>
        <button class="btn btn-primary" data-save="publish"><i data-icon="send" class="sm"></i>نشر</button>
      </div>
    </div>

    <div class="grid g-side">
      <div class="card" style="min-width:0">
        <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
          <input class="input title-input" id="title" placeholder="عنوان المقال…" aria-label="عنوان المقال">
          <input class="input" id="excerpt" placeholder="مقدمة قصيرة تظهر تحت العنوان وفي بطاقة المقال" aria-label="المقدمة">
          <div>
            <div class="editor-toolbar" role="toolbar" aria-label="أدوات التنسيق">
              <button type="button" data-md="heading" title="عنوان فرعي"><i data-icon="heading" class="sm"></i></button>
              <button type="button" data-md="bold" title="غامق"><i data-icon="bold" class="sm"></i></button>
              <button type="button" data-md="italic" title="مائل"><i data-icon="italic" class="sm"></i></button>
              <span class="sep"></span>
              <button type="button" data-md="list" title="قائمة"><i data-icon="list" class="sm"></i></button>
              <button type="button" data-md="quote" title="اقتباس / نصيحة"><i data-icon="quote" class="sm"></i></button>
              <button type="button" data-md="code" title="كود"><i data-icon="code" class="sm"></i></button>
              <span class="sep"></span>
              <button type="button" data-md="link" title="رابط"><i data-icon="link" class="sm"></i></button>
              <button type="button" data-md="image" title="صورة"><i data-icon="image" class="sm"></i></button>
            </div>
            <textarea class="textarea editor-area" id="body" placeholder="ابدأ الكتابة هنا… (يدعم Markdown)" aria-label="محتوى المقال"></textarea>
          </div>
          <div class="legend"><span><i data-icon="article" class="sm"></i><b id="words">0</b> كلمة</span><span><i data-icon="clock" class="sm"></i><b id="readTime">0</b> دقائق قراءة</span><span><i data-icon="heading" class="sm"></i><b id="headings">0</b> عناوين فرعية</span></div>
        </div>
      </div>

      <aside style="display:flex;flex-direction:column;gap:20px;min-width:0">
        <div class="card">
          <div class="card-head"><h3>النشر</h3></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
            <div class="field"><label for="status">الحالة</label><select class="select" id="status"><option>مسودة</option><option>مجدول</option><option>منشور</option></select></div>
            <div class="field" id="scheduleField" hidden><label for="publishAt">موعد النشر</label><input class="input" type="datetime-local" id="publishAt"></div>
            <div class="field"><label for="category">التصنيف</label><select class="select" id="category"><option>دروس عملية</option><option>خلف الكواليس</option><option>العمل الحر</option><option>أدوات و AI</option></select></div>
            <label class="switch"><input type="checkbox" id="featured"><span class="track"></span>مقال مميز في الصفحة الرئيسية</label>
            <label class="switch"><input type="checkbox" id="newsletter" checked><span class="track"></span>إرساله في نشرة البطّة</label>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><h3>صورة المقال</h3></div>
          <div class="card-body">
            <label class="dropzone" id="cover"><input type="file" accept="image/*" aria-label="رفع صورة"><i data-icon="upload"></i><b>ارفع صورة المقال</b><small>1200×630 بكسل</small></label>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><h3>محركات البحث (SEO)</h3></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
            <div class="field"><label for="metaTitle">عنوان SEO</label><input class="input" id="metaTitle" maxlength="60"><span class="hint"><span id="mtCount">0</span>/60</span></div>
            <div class="field"><label for="metaDesc">وصف SEO</label><textarea class="textarea" id="metaDesc" rows="3" maxlength="160" style="min-height:80px"></textarea><span class="hint"><span id="mdCount">0</span>/160</span></div>
            <div class="seo-preview" aria-label="معاينة نتيجة البحث">
              <div class="u">batta.dev › articles › <span id="pvSlug">new-article</span></div>
              <div class="t" id="pvTitle">عنوان المقال سيظهر هنا</div>
              <div class="d" id="pvDesc">وصف المقال في نتائج البحث سيظهر هنا.</div>
            </div>
          </div>
        </div>
      </aside>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/article-editor.js') }}"></script>
@endpush
