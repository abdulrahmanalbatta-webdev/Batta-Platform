@extends('layouts.dashboard')

@section('title', 'محتوى الموقع')
@section('page', 'site-content')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>محتوى الموقع</span></div>
        <h1>محتوى الموقع</h1>
        <p>نصوص الموقع العام: شريط الإعلان، الرئيسية، صفحة "من أنا"، الخدمات والباقات، الآراء والأسئلة. يظهر كل قسم في الموقع فور حفظه.</p>
      </div>
      <div class="page-actions">
        <a class="btn btn-ghost" id="viewSite" target="_blank" rel="noopener"><i data-icon="external" class="sm"></i>عرض الموقع</a>
      </div>
    </div>

    <div class="card">
      <div class="tabs" data-tabs="content" id="contentTabs" role="tablist"></div>
      <div id="contentPanels"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/site-content.js') }}"></script>
@endpush
