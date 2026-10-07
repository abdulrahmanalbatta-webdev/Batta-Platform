@extends('layouts.dashboard')

@section('title', 'التعليقات')
@section('page', 'comments')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>التعليقات</span></div>
        <h1>التعليقات</h1>
        <p>راجع تعليقات الطلاب وردودهم على المقالات والدورات والورش قبل ظهورها، وردّ عليها.</p>
      </div>
    </div>

    <div class="card">
      <div class="toolbar">
        <div class="seg" id="statusSeg"></div>
        <span class="grow"></span>
        <select class="select" style="width:auto;height:40px" id="placeFilter" aria-label="المكان"><option value="">كل الصفحات</option></select>
      </div>
      <div id="commentList"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/comments.js') }}"></script>
@endpush
