@extends('layouts.dashboard')

@section('title', 'المقالات')
@section('page', 'articles')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>المقالات</span></div>
        <h1>المقالات</h1>
        <p>كتابة المقالات وجدولتها ومتابعة قراءاتها.</p>
      </div>
      <div class="page-actions">
        <a class="btn btn-primary" href="{{ route('articles.create') }}"><i data-icon="edit" class="sm"></i>مقال جديد</a>
      </div>
    </div>

    <div class="grid g4" id="artStats"></div>

    <div class="card">
      <div class="toolbar">
        <label class="search"><i data-icon="search" class="sm"></i><input id="q" type="search" placeholder="ابحث بعنوان المقال…" aria-label="بحث"></label>
        <div class="seg" id="statusSeg"></div>
        <span class="grow"></span>
        <select class="select" id="catFilter" style="width:auto;height:40px" aria-label="التصنيف"><option value="">كل التصنيفات</option></select>
      </div>
      <div id="table"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/articles.js') }}"></script>
@endpush
