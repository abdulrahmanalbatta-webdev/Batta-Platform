@extends('layouts.dashboard')

@section('title', 'التحليلات')
@section('page', 'analytics')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>التحليلات</span></div>
        <h1>التحليلات</h1>
        <p id="periodNote">التسجيلات والطلاب خلال الفترة، مقارنة بالفترة التي قبلها.</p>
      </div>
      <div class="page-actions">
        <select class="select" style="width:auto" id="period" aria-label="الفترة">
          <option value="7">آخر 7 أيام</option>
          <option value="30" selected>آخر 30 يوماً</option>
          <option value="90">آخر 90 يوماً</option>
          <option value="365">آخر 12 شهراً</option>
        </select>
        <button class="btn btn-ghost" id="exportReport"><i data-icon="download" class="sm"></i>تصدير</button>
      </div>
    </div>

    <div class="grid g4" id="kpis"></div>

    <div class="card">
      <div class="card-head">
        <div><h3 id="seriesTitle">التسجيلات</h3><p id="seriesNote"></p></div>
        <div class="seg" id="metric">
          <button class="on" data-m="registrations">التسجيلات</button>
          <button data-m="students">الطلاب الجدد</button>
        </div>
      </div>
      <div class="card-body"><div id="seriesChart"></div></div>
    </div>

    <div class="grid g-main">
      <div class="card">
        <div class="card-head"><div><h3>رحلة الطلاب الجدد</h3><p>من إنشاء الحساب خلال الفترة حتى التسجيل</p></div></div>
        <div class="card-body" id="funnel"></div>
      </div>
      <div class="card">
        <div class="card-head"><div><h3>الدورات والورش</h3><p>نسبة التسجيلات</p></div></div>
        <div class="card-body">
          <div id="splitChart"></div>
          <div class="legend" id="splitLegend" style="justify-content:center;margin-top:14px;flex-wrap:wrap"></div>
        </div>
      </div>
    </div>

    <div class="grid g-main">
      <div class="card">
        <div class="card-head"><div><h3>الأكثر تسجيلاً</h3><p>الدورات والورش خلال الفترة</p></div></div>
        <div class="table-wrap">
          <table class="table" style="min-width:480px">
            <thead><tr><th>الدورة أو الورشة</th><th>التسجيلات</th><th style="width:35%">النسبة</th></tr></thead>
            <tbody id="topItems"></tbody>
          </table>
        </div>
      </div>
      <div class="card">
        <div class="card-head"><div><h3>دول المسجّلين</h3><p>من سجّلوا خلال الفترة</p></div></div>
        <div class="card-body" id="countries"></div>
      </div>
    </div>

    <!-- traffic of the public site (Google Analytics 4, GET analytics/traffic) -->
    <div class="page-head" style="margin-top:12px">
      <div><h2 style="font-size:18px">زيارات الموقع</h2><p id="trafficNote">من Google Analytics، لنفس الفترة.</p></div>
    </div>
    <div id="trafficEmpty" class="card" hidden><div class="card-body" style="display:flex;gap:14px;align-items:flex-start"><span class="kpi-ico c-amber" id="trafficEmptyIcon"></span><div><b style="color:var(--fg)" id="trafficEmptyTitle"></b><p class="muted" id="trafficEmptyText" style="margin-top:4px"></p></div></div></div>
    <div id="traffic" hidden>
      <div class="grid g4" id="trafficKpis"></div>
      <div class="card">
        <div class="card-head"><div><h3>الزوار والجلسات</h3><p id="trafficSeriesNote"></p></div></div>
        <div class="card-body"><div id="trafficChart"></div></div>
      </div>
      <div class="grid g3">
        <div class="card">
          <div class="card-head"><div><h3>مصادر الزيارات</h3><p>نسبة الجلسات</p></div></div>
          <div class="card-body" id="trafficSources"></div>
        </div>
        <div class="card">
          <div class="card-head"><div><h3>الأجهزة</h3><p>نسبة الجلسات</p></div></div>
          <div class="card-body">
            <div id="devicesChart"></div>
            <div class="legend" id="devicesLegend" style="justify-content:center;margin-top:14px;flex-wrap:wrap"></div>
          </div>
        </div>
        <div class="card">
          <div class="card-head"><div><h3>الصفحات الأكثر زيارة</h3><p>مشاهدات الصفحة</p></div></div>
          <div class="card-body" id="trafficPages"></div>
        </div>
      </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/charts.js') }}"></script>
    <script src="{{ asset('assets/dashboard/js/pages/analytics.js') }}"></script>
@endpush
