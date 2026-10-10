@extends('layouts.dashboard')

@section('title', 'المسارات')
@section('page', 'paths')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>المسارات</span></div>
        <h1>المسارات التعليمية</h1>
        <p>خرائط التعلّم في صفحة "المسارات" بالموقع: لكل تخصص مراحل بالترتيب، وفي كل مرحلة مصادر مجانية ودوراتك وورشك ومقالاتك.</p>
      </div>
      <div class="page-actions">
        <a class="btn btn-primary" href="{{ route('paths.create') }}" data-requires="manage_content"><i data-icon="plus" class="sm"></i>مسار جديد</a>
      </div>
    </div>

    <div class="grid g4" id="pathStats"></div>

    <div class="card">
      <div class="toolbar">
        <div class="seg" id="statusSeg"></div>
        <span class="grow"></span>
        <label class="search"><i data-icon="search" class="sm"></i><input id="q" type="search" placeholder="ابحث عن مسار…" aria-label="بحث"></label>
      </div>
      <div class="card-body">
        <div class="path-list" id="list"></div>
      </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/paths.js') }}"></script>
@endpush
