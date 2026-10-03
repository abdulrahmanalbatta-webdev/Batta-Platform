@extends('layouts.dashboard')

@section('title', 'التحليلات')
@section('page', 'analytics')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>التحليلات</span></div>
        <h1>التحليلات</h1>
        <p>أداء الموقع والتحويل خلال آخر 30 يوماً.</p>
      </div>
      <div class="page-actions">
        <select class="select" style="width:auto" aria-label="الفترة"><option>آخر 30 يوماً</option><option>آخر 7 أيام</option><option>آخر 90 يوماً</option><option>هذه السنة</option></select>
        <button class="btn btn-ghost" id="exportTraffic"><i data-icon="download" class="sm"></i>تصدير</button>
      </div>
    </div>

    <div class="grid g4">
      <div class="card kpi"><div class="kpi-top"><span class="kpi-label">الزيارات</span><span class="kpi-ico c-blue"><i data-icon="eye"></i></span></div><div class="kpi-value">21,400</div><div class="kpi-note"><span class="trend up">+22%</span> عن الفترة السابقة</div></div>
      <div class="card kpi"><div class="kpi-top"><span class="kpi-label">حسابات جديدة</span><span class="kpi-ico c-violet"><i data-icon="users"></i></span></div><div class="kpi-value">1,480</div><div class="kpi-note"><span class="trend up">+15%</span> عن الفترة السابقة</div></div>
      <div class="card kpi"><div class="kpi-top"><span class="kpi-label">معدل التحويل</span><span class="kpi-ico c-green"><i data-icon="trend"></i></span></div><div class="kpi-value">1.8%</div><div class="kpi-note"><span class="trend up">+0.3%</span> زيارة ← شراء</div></div>
      <div class="card kpi"><div class="kpi-top"><span class="kpi-label">متوسط مدة الزيارة</span><span class="kpi-ico c-amber"><i data-icon="clock"></i></span></div><div class="kpi-value">3:24</div><div class="kpi-note"><span class="trend down">-0:08</span> دقيقة</div></div>
    </div>

    <div class="card">
      <div class="card-head">
        <div><h3>الزيارات اليومية</h3><p>سبتمبر 2026</p></div>
        <div class="seg" id="metric">
          <button class="on" data-m="visits">الزيارات</button>
          <button data-m="signups">الحسابات الجديدة</button>
        </div>
      </div>
      <div class="card-body"><div id="trafficChart"></div></div>
    </div>

    <div class="grid g-main">
      <div class="card">
        <div class="card-head"><div><h3>قمع التحويل</h3><p>من الزيارة حتى الشراء</p></div></div>
        <div class="card-body" id="funnel"></div>
      </div>
      <div class="card">
        <div class="card-head"><div><h3>الأجهزة</h3><p>نسبة الزيارات</p></div></div>
        <div class="card-body">
          <div id="devicesChart"></div>
          <div class="legend" style="justify-content:center;margin-top:14px">
            <span><i style="background:#0066ff"></i>جوال 61%</span>
            <span><i style="background:#0b0d12"></i>كمبيوتر 33%</span>
            <span><i style="background:#94a3b8"></i>تابلت 6%</span>
          </div>
        </div>
      </div>
    </div>

    <div class="grid g-main">
      <div class="card">
        <div class="card-head"><div><h3>الصفحات الأكثر زيارة</h3></div></div>
        <div class="table-wrap">
          <table class="table" style="min-width:560px">
            <thead><tr><th>الصفحة</th><th>المشاهدات</th><th>متوسط القراءة</th><th style="width:30%">النسبة</th></tr></thead>
            <tbody id="topPages"></tbody>
          </table>
        </div>
      </div>
      <div class="card">
        <div class="card-head"><div><h3>الدول</h3></div></div>
        <div class="card-body" id="countries"></div>
      </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/charts.js') }}"></script>
    <script src="{{ asset('assets/dashboard/js/pages/analytics.js') }}"></script>
@endpush
