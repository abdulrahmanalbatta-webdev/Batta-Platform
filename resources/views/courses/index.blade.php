@extends('layouts.dashboard')

@section('title', 'الدورات')
@section('page', 'courses')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>الدورات</span></div>
        <h1>الدورات</h1>
        <p>إدارة الدورات وأسعارها وحالة نشرها.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-ghost" id="exportCourses"><i data-icon="download" class="sm"></i>تصدير</button>
        <a class="btn btn-primary" href="{{ route('courses.create') }}"><i data-icon="plus" class="sm"></i>دورة جديدة</a>
      </div>
    </div>

    <div class="grid g4" id="courseStats"></div>

    <div class="card">
      <div class="toolbar">
        <label class="search"><i data-icon="search" class="sm"></i><input id="q" type="search" placeholder="ابحث باسم الدورة…" aria-label="بحث"></label>
        <div class="seg" id="statusSeg"></div>
        <span class="grow"></span>
        <select class="select" id="levelFilter" style="width:auto;height:40px" aria-label="المستوى">
          <option value="">كل المستويات</option><option>مبتدئ</option><option>متوسط</option><option>متقدم</option>
        </select>
        <div class="seg" id="viewSeg" aria-label="طريقة العرض">
          <button class="on" data-view="table" title="جدول"><i data-icon="list" class="sm"></i></button>
          <button data-view="grid" title="بطاقات"><i data-icon="grid" class="sm"></i></button>
        </div>
      </div>
      <div class="bulkbar" id="bulkbar" hidden>
        <span id="bulkCount"></span><span class="grow"></span>
        <button class="btn btn-soft btn-sm" id="bulkPublish"><i data-icon="check" class="sm"></i>نشر</button>
        <button class="btn btn-ghost btn-sm" id="bulkDraft">تحويل لمسودة</button>
        <button class="btn btn-danger-soft btn-sm" id="bulkDelete"><i data-icon="trash" class="sm"></i>حذف</button>
      </div>
      <div id="tableView"></div>
      <div id="gridView" class="card-body grid g3" hidden></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/courses.js') }}"></script>
@endpush
