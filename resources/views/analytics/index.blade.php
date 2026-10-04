@extends('layouts.dashboard')

@section('title', 'التحليلات')
@section('page', 'analytics')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>التحليلات</span></div>
        <h1>التحليلات</h1>
        <p id="periodNote">المبيعات والطلاب خلال الفترة، مقارنة بالفترة التي قبلها.</p>
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
        <div><h3 id="seriesTitle">الإيرادات</h3><p id="seriesNote"></p></div>
        <div class="seg" id="metric">
          <button class="on" data-m="revenue">الإيرادات</button>
          <button data-m="students">الطلاب الجدد</button>
        </div>
      </div>
      <div class="card-body"><div id="seriesChart"></div></div>
    </div>

    <div class="grid g-main">
      <div class="card">
        <div class="card-head"><div><h3>رحلة الطلاب الجدد</h3><p>من التسجيل خلال الفترة حتى الشراء</p></div></div>
        <div class="card-body" id="funnel"></div>
      </div>
      <div class="card">
        <div class="card-head"><div><h3>طرق الدفع</h3><p>نسبة الطلبات المكتملة</p></div></div>
        <div class="card-body">
          <div id="methodsChart"></div>
          <div class="legend" id="methodsLegend" style="justify-content:center;margin-top:14px;flex-wrap:wrap"></div>
        </div>
      </div>
    </div>

    <div class="grid g-main">
      <div class="card">
        <div class="card-head"><div><h3>الأكثر مبيعاً</h3><p>حسب الإيرادات</p></div></div>
        <div class="table-wrap">
          <table class="table" style="min-width:560px">
            <thead><tr><th>المنتج</th><th>الطلبات</th><th>الإيرادات</th><th style="width:30%">النسبة</th></tr></thead>
            <tbody id="topProducts"></tbody>
          </table>
        </div>
      </div>
      <div class="card">
        <div class="card-head"><div><h3>دول المشترين</h3><p>من دفعوا خلال الفترة</p></div></div>
        <div class="card-body" id="countries"></div>
      </div>
    </div>

    <p class="muted" style="font-size:12.5px;margin-top:4px">بيانات الزيارات (المصادر، الأجهزة، الصفحات) تحتاج أداة إحصاءات للموقع العام مثل Plausible أو Google Analytics، وتُضاف عند ربطها.</p>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/charts.js') }}"></script>
    <script src="{{ asset('assets/dashboard/js/pages/analytics.js') }}"></script>
@endpush
