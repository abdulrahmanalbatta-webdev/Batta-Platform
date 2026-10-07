@extends('layouts.dashboard')

@section('title', 'لوحة المعلومات')
@section('page', 'dashboard')

@section('content')
    <div class="page-head">
      <div>
        <h1 id="greeting">مرحباً، عبدالرحمن</h1>
        <p id="today">إليك ملخص أداء المنصة هذا الشهر.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-ghost" id="exportReport"><i data-icon="download" class="sm"></i>تصدير التقرير</button>
        <a class="btn btn-primary" href="{{ route('courses.create') }}"><i data-icon="plus" class="sm"></i>دورة جديدة</a>
      </div>
    </div>

    <!-- KPIs: this month so far against the same days of last month -->
    <div class="grid g4" id="kpis">
      <div class="card kpi dark">
        <div class="kpi-top"><span class="kpi-label" id="kpiEnrollmentsLabel">تسجيلات الدورات</span><span class="kpi-ico c-glass"><i data-icon="award"></i></span></div>
        <div class="kpi-value" id="kpiEnrollments">—</div>
        <div class="kpi-bottom"><span class="kpi-note" id="kpiEnrollmentsNote"></span><span data-spark="enrollments" data-color="#5c9dff"></span></div>
      </div>
      <div class="card kpi">
        <div class="kpi-top"><span class="kpi-label">تسجيلات الورش</span><span class="kpi-ico c-green"><i data-icon="calendar"></i></span></div>
        <div class="kpi-value" id="kpiWorkshops">—</div>
        <div class="kpi-bottom"><span class="kpi-note" id="kpiWorkshopsNote"></span><span data-spark="workshop_registrations" data-color="#0e9f6e"></span></div>
      </div>
      <div class="card kpi">
        <div class="kpi-top"><span class="kpi-label">طلاب جدد</span><span class="kpi-ico c-blue"><i data-icon="users"></i></span></div>
        <div class="kpi-value" id="kpiStudents">—</div>
        <div class="kpi-bottom"><span class="kpi-note" id="kpiStudentsNote"></span><span data-spark="students" data-color="#0066ff"></span></div>
      </div>
      <div class="card kpi">
        <div class="kpi-top"><span class="kpi-label">طلبات مشاريع</span><span class="kpi-ico c-amber"><i data-icon="briefcase"></i></span></div>
        <div class="kpi-value" id="kpiLeads">—</div>
        <div class="kpi-bottom"><span class="kpi-note" id="kpiLeadsNote"></span><span data-spark="leads" data-color="#c27803"></span></div>
      </div>
    </div>

    <!-- registrations + mix -->
    <div class="grid g-main">
      <div class="card">
        <div class="card-head">
          <div><h3>التسجيلات</h3><p>الدورات والورش شهرياً</p></div>
          <div class="seg" id="regRange">
            <button class="on" data-range="12">12 شهراً</button>
            <button data-range="6">6 أشهر</button>
            <button data-range="3">3 أشهر</button>
          </div>
        </div>
        <div class="card-body">
          <div class="legend" style="margin-bottom:10px">
            <span><i style="background:#0066ff"></i>الدورات</span>
            <span><i style="background:#0891b2"></i>الورش</span>
          </div>
          <div id="registrationsChart"></div>
        </div>
        <div class="stat-row">
          <div class="mini-stat"><b id="regTotal">—</b><small>إجمالي الفترة</small></div>
          <div class="mini-stat"><b id="regCourses">—</b><small>في الدورات</small></div>
          <div class="mini-stat"><b id="regWorkshops">—</b><small>في الورش</small></div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><div><h3>توزيع التسجيلات</h3><p>آخر 30 يوماً</p></div><a class="link" href="{{ route('analytics') }}">التفاصيل</a></div>
        <div class="card-body">
          <div id="mixChart"></div>
          <div class="list" id="mixList" style="margin-top:16px"></div>
        </div>
      </div>
    </div>

    <!-- registrations + activity -->
    <div class="grid g-main">
      <div class="card">
        <div class="card-head"><div><h3>أحدث التسجيلات</h3><p>آخر من سجّل في دورة أو ورشة</p></div><a class="btn btn-ghost btn-sm" href="{{ route('students.index') }}">كل الطلاب <i data-icon="arrow" class="sm"></i></a></div>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>الطالب</th><th>سجّل في</th><th>النوع</th><th>التاريخ</th></tr></thead>
            <tbody id="recentRegistrations"></tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><div><h3>آخر النشاطات</h3><p>ما حدث مؤخراً</p></div></div>
        <div class="card-body" style="padding-inline:0">
          <ul class="timeline" id="activity"></ul>
        </div>
      </div>
    </div>

    <!-- bottom widgets -->
    <div class="grid g3">
      <div class="card">
        <div class="card-head"><div><h3>الدورات الأكثر تسجيلاً</h3></div><a class="link" href="{{ route('courses.index') }}">الكل</a></div>
        <div class="list" id="topCourses"></div>
      </div>
      <div class="card">
        <div class="card-head"><div><h3>الورش القادمة</h3></div><a class="link" href="{{ route('workshops.index') }}">الكل</a></div>
        <div class="list" id="upcomingWorkshops"></div>
      </div>
      <div class="card">
        <div class="card-head"><div><h3>طلبات المشاريع</h3></div><a class="link" href="{{ route('leads.index') }}">اللوحة</a></div>
        <div class="card-body" id="pipeline"></div>
      </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/charts.js') }}"></script>
    <script src="{{ asset('assets/dashboard/js/pages/dashboard.js') }}"></script>
@endpush
