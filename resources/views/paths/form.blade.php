@extends('layouts.dashboard')

@use('App\Models\LearningPath')
@use('App\Support\SiteContent')

@section('title', 'مسار جديد')
@section('page', 'path-form')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><a href="{{ route('paths.index') }}">المسارات</a><span class="sep">/</span><span id="crumb">مسار جديد</span></div>
        <h1 id="pageTitle">مسار جديد</h1>
        <p>اكتب بيانات المسار، ثم رتّب مراحله: في كل مرحلة مواضيع ومصادر مجانية، واربط بها دوراتك وورشك ومقالاتك.</p>
      </div>
      <div class="page-actions">
        <a class="btn btn-ghost" href="{{ route('paths.index') }}">إلغاء</a>
        <a class="btn btn-ghost" id="viewOnSite" target="_blank" rel="noopener" hidden><i data-icon="external" class="sm"></i>عرض في الموقع</a>
        <button class="btn btn-primary" type="button" id="save"><i data-icon="save" class="sm"></i>حفظ المسار</button>
      </div>
    </div>

    <form class="grid g-side" id="pathForm" novalidate>
      <div style="display:flex;flex-direction:column;gap:20px;min-width:0">
        <div class="card">
          <div class="card-head"><h3>معلومات المسار</h3></div>
          <div class="card-body form-grid">
            <div class="field full">
              <label for="title">اسم المسار *</label>
              <input class="input" id="title" maxlength="80" placeholder="مثلاً: مطوّر واجهات Frontend">
            </div>
            <div class="field full">
              <label for="slug">رابط المسار</label>
              <div class="input-group"><span class="addon ltr">{{ parse_url($siteUrl, PHP_URL_HOST) }}/paths/</span><input class="input ltr" id="slug" maxlength="60" placeholder="frontend" style="text-align:left"></div>
            </div>
            <div class="field full">
              <label for="summary">الوصف المختصر</label>
              <textarea class="textarea" id="summary" rows="2" maxlength="300" placeholder="جملة أو جملتان تظهر في بطاقة المسار وأعلى صفحته"></textarea>
              <span class="hint"><span id="summaryCount">0</span>/300 حرفاً</span>
            </div>
            <div class="field">
              <label for="audience">لمن هذا المسار</label>
              <input class="input" id="audience" maxlength="120" placeholder="مثلاً: للمبتدئين تماماً">
            </div>
            <div class="field">
              <label for="duration">المدة التقريبية</label>
              <input class="input" id="duration" maxlength="30" placeholder="مثلاً: 6–9 أشهر">
            </div>
            <div class="field full">
              <label>في آخر المسار يستطيع الطالب</label>
              <div class="tags-input" id="outcomes"><input placeholder="اكتب نقطة واضغط Enter" aria-label="مخرجات المسار"></div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-head">
            <h3>المراحل</h3>
            <span class="muted" id="stagesNote"></span>
          </div>
          <div class="card-body">
            <ol class="stage-list" id="stages"></ol>
            <button type="button" class="btn btn-ghost" id="addStage"><i data-icon="plus" class="sm"></i>إضافة مرحلة</button>
          </div>
        </div>
      </div>

      <aside style="display:flex;flex-direction:column;gap:20px;min-width:0">
        <div class="card">
          <div class="card-head"><h3>النشر</h3></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
            <label class="switch"><input type="checkbox" id="published"><span class="track"></span>ظاهر في الموقع</label>
            <p class="hint" style="margin:0">المسار المخفي يبقى محفوظاً هنا ولا يظهر للطلاب.</p>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><h3>الأيقونة</h3></div>
          <div class="card-body">
            <select class="select" id="icon" aria-label="الأيقونة">
              @foreach (SiteContent::ICONS as $key => $label)
                <option value="{{ $key }}">{{ $label }} ({{ $key }})</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><h3>ملخّص المسار</h3></div>
          <div class="card-body path-summary" id="summaryBox"></div>
        </div>
      </aside>
    </form>

    {{-- the options the stage editor offers (read by js/pages/path-form.js) --}}
    <script type="application/json" id="pathOptions">@json(['resource_types' => LearningPath::RESOURCE_TYPES, 'languages' => LearningPath::LANGUAGES, 'item_types' => LearningPath::ITEM_TYPES])</script>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/path-form.js') }}"></script>
@endpush
