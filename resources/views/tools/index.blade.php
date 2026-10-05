@extends('layouts.dashboard')

@section('title', 'الأدوات')
@section('page', 'tools')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>الأدوات</span></div>
        <h1>الأدوات البرمجية</h1>
        <p>الأدوات التي تظهر في صفحة "أدواتي" بالموقع: أضف، عدّل، رتّب، وأخفِ ما تريد.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-ghost" id="manageCats" data-requires="manage_content"><i data-icon="layers" class="sm"></i>التصنيفات</button>
        <button class="btn btn-ghost" id="export"><i data-icon="download" class="sm"></i>تصدير CSV</button>
        <button class="btn btn-primary" id="addTool" data-requires="manage_content"><i data-icon="plus" class="sm"></i>أداة جديدة</button>
      </div>
    </div>

    <div class="grid g4" id="toolStats"></div>

    <div class="card">
      <div class="toolbar">
        <div class="seg" id="catSeg"></div>
        <span class="grow"></span>
        <label class="search"><i data-icon="search" class="sm"></i><input id="q" type="search" placeholder="ابحث عن أداة…" aria-label="بحث"></label>
        <select class="select" style="width:auto;height:40px" id="statusFilter" aria-label="الحالة"><option value="">كل الحالات</option><option>منشور</option><option>مسودة</option></select>
      </div>
      <div class="card-body">
        <div class="tool-grid" id="grid"></div>
      </div>
    </div>
@endsection

@push('modals')
  <div class="modal" id="toolModal" aria-hidden="true">
    <form class="modal-box" id="toolForm" novalidate style="width:min(640px, 100%)">
      <div class="modal-head"><h3 id="toolModalTitle">أداة جديدة</h3><button type="button" class="btn-icon" data-close aria-label="إغلاق"><i data-icon="close"></i></button></div>
      <div class="modal-body">
        <div class="tool-preview" id="preview"></div>
        <div class="form-grid" style="margin-top:18px">
          <div class="field"><label for="tName">اسم الأداة *</label><input class="input" id="tName" placeholder="مثلاً: VS Code" maxlength="40"></div>
          <div class="field"><label for="tShort">الرمز في الشعار</label><input class="input ltr" id="tShort" placeholder="VS" maxlength="3"><span class="hint">حرفان أو ثلاثة. يُقترح تلقائياً من الاسم.</span></div>
          <div class="field">
            <label for="tCategory">التصنيف *</label>
            <select class="select" id="tCategory"></select>
            <div class="inline-add" id="newCatRow" hidden>
              <input class="input" id="newCatName" placeholder="اسم التصنيف الجديد" maxlength="30">
              <button type="button" class="btn btn-sm btn-primary" id="newCatSave">إضافة</button>
              <button type="button" class="btn-icon" id="newCatCancel" aria-label="إلغاء"><i data-icon="close" class="sm"></i></button>
            </div>
          </div>
          <div class="field"><label for="tSince">أستخدمها منذ</label><input class="input ltr" id="tSince" type="number" min="2000" max="2030"></div>
          <div class="field full"><label for="tUrl">رابط الأداة</label><input class="input ltr" id="tUrl" type="url" placeholder="https://"></div>
          <div class="field full"><label for="tWhy">لماذا أستخدمها؟ *</label><textarea class="textarea" id="tWhy" rows="3" maxlength="120" placeholder="جملة قصيرة تظهر تحت اسم الأداة في الموقع"></textarea><span class="hint"><span id="whyCount">0</span> / 120</span></div>
          <div class="field">
            <label>لون الشعار</label>
            <div class="swatches" id="swatches"></div>
          </div>
          <div class="field">
            <label for="tStatus">الحالة</label>
            <select class="select" id="tStatus"><option>منشور</option><option>مسودة</option></select>
          </div>
          <div class="field full">
            <label class="check"><input type="checkbox" id="tAffiliate">رابط تسويق بالعمولة (Affiliate)</label>
            <span class="hint">يظهر بجانب الأداة وسم صغير "رابط شراكة" التزاماً بالشفافية.</span>
          </div>
        </div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>إلغاء</button><button class="btn btn-primary" type="submit" id="toolSubmit">إضافة الأداة</button></div>
    </form>
  </div>
@endpush

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/tools.js') }}"></script>
@endpush
