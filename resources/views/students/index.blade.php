@extends('layouts.dashboard')

@section('title', 'الطلاب')
@section('page', 'students')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>الطلاب</span></div>
        <h1>الطلاب والأعضاء</h1>
        <p>كل الحسابات المسجلة في المنصة وتقدّمها في الدورات.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-ghost" id="export"><i data-icon="download" class="sm"></i>تصدير CSV</button>
        <button class="btn btn-primary" data-open="mailModal" id="mailAll" data-requires="manage_students"><i data-icon="mail" class="sm"></i>رسالة جماعية</button>
      </div>
    </div>

    <div class="grid g4" id="studentStats"></div>

    <div class="card">
      <div class="toolbar">
        <div class="seg" id="statusSeg"></div>
        <span class="grow"></span>
        <label class="search"><i data-icon="search" class="sm"></i><input id="q" type="search" placeholder="ابحث بالاسم أو البريد…" aria-label="بحث"></label>
        <select class="select" style="width:auto;height:40px" id="countryFilter" aria-label="الدولة"><option value="">كل الدول</option></select>
        <select class="select" style="width:auto;height:40px" id="planFilter" aria-label="التسجيل"><option value="">الكل</option><option value="registered">مسجّلون في دورة أو ورشة</option><option value="none">غير مسجّلين</option></select>
      </div>
      <div class="bulkbar" id="bulk" hidden>
        <b id="bulkCount"></b>
        <button class="btn btn-sm btn-soft" data-open="mailModal"><i data-icon="mail" class="sm"></i>مراسلة</button>
        <button class="btn btn-sm btn-ghost" id="bulkActivate">تفعيل</button>
        <button class="btn btn-sm btn-danger-soft" id="bulkSuspend">إيقاف</button>
      </div>
      <div id="table"></div>
    </div>
@endsection

@push('modals')
  <div class="modal" id="mailModal" aria-hidden="true">
    <form class="modal-box" id="mailForm" novalidate>
      <div class="modal-head"><h3>رسالة بريد</h3><button type="button" class="btn-icon" data-close aria-label="إغلاق"><i data-icon="close"></i></button></div>
      <div class="modal-body" style="display:flex;flex-direction:column;gap:16px">
        <div class="field"><label>المستلمون</label><div class="badge" id="mailTo" style="width:fit-content"></div></div>
        <div class="field"><label for="mSubject">الموضوع *</label><input class="input" id="mSubject" placeholder="مثال: دفعة جديدة من برنامج المطوّر المستقل"></div>
        <div class="field"><label for="mBody">الرسالة *</label><textarea class="textarea" id="mBody" rows="6" placeholder="مرحباً {الاسم}،"></textarea><span class="hint">استخدم {الاسم} ليُستبدل باسم كل طالب.</span></div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>إلغاء</button><button class="btn btn-primary" type="submit"><i data-icon="send" class="sm"></i>إرسال</button></div>
    </form>
  </div>
@endpush

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/students.js') }}"></script>
@endpush
