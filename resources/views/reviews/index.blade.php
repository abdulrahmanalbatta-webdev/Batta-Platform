@extends('layouts.dashboard')

@section('title', 'التقييمات')
@section('page', 'reviews')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>التقييمات</span></div>
        <h1>التقييمات والمراجعات</h1>
        <p>راجع آراء الطلاب، ردّ عليها، وتحكّم بما يظهر في الموقع.</p>
      </div>
    </div>

    <div class="grid g-side">
      <div class="card">
        <div class="toolbar">
          <div class="seg" id="statusSeg"></div>
          <span class="grow"></span>
          <select class="select" style="width:auto;height:40px" id="courseFilter" aria-label="الدورة"><option value="">كل الدورات</option></select>
          <select class="select" style="width:auto;height:40px" id="starFilter" aria-label="التقييم"><option value="">كل التقييمات</option><option>5</option><option>4</option><option>3</option><option>2</option><option>1</option></select>
        </div>
        <div id="reviewList"></div>
      </div>

      <div style="display:flex;flex-direction:column;gap:20px">
        <div class="card">
          <div class="card-head"><h3>ملخص التقييم</h3></div>
          <div class="card-body" id="summary"></div>
        </div>
        <div class="card">
          <div class="card-head"><h3>حسب الدورة</h3></div>
          <div class="list" id="byCourse"></div>
        </div>
      </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/reviews.js') }}"></script>
@endpush
