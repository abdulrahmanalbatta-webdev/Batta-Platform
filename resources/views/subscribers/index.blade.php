@extends('layouts.dashboard')

@section('title', 'النشرة البريدية')
@section('page', 'subscribers')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>النشرة البريدية</span></div>
        <h1>النشرة البريدية</h1>
        <p>من اشترك من الموقع. كل مقال جديد يصلهم بالبريد مع الطلاب، إذا كانت "النشرة البريدية" مفعّلة في الإعدادات ← الإشعارات.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-ghost" id="export"><i data-icon="download" class="sm"></i>تصدير CSV</button>
      </div>
    </div>

    <div class="grid g3" id="subStats"></div>

    <div class="card">
      <div class="toolbar">
        <label class="search"><i data-icon="search" class="sm"></i><input id="q" type="search" placeholder="ابحث بالبريد…" aria-label="بحث"></label>
        <div class="seg" id="statusSeg"></div>
      </div>
      <div id="table"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/subscribers.js') }}"></script>
@endpush
