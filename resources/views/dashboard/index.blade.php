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

    <!-- KPIs -->
    <div class="grid g4">
      <div class="card kpi dark">
        <div class="kpi-top"><span class="kpi-label">إيرادات سبتمبر</span><span class="kpi-ico c-glass"><i data-icon="dollar"></i></span></div>
        <div class="kpi-value">12,000$</div>
        <div class="kpi-bottom"><span class="kpi-note"><span class="trend up">+14%</span> عن أغسطس</span><span data-spark="revenue" data-color="#5c9dff"></span></div>
      </div>
      <div class="card kpi">
        <div class="kpi-top"><span class="kpi-label">طلاب جدد</span><span class="kpi-ico c-blue"><i data-icon="users"></i></span></div>
        <div class="kpi-value">128</div>
        <div class="kpi-bottom"><span class="kpi-note"><span class="trend up">+18%</span> هذا الشهر</span><span data-spark="students" data-color="#0066ff"></span></div>
      </div>
      <div class="card kpi">
        <div class="kpi-top"><span class="kpi-label">الطلبات المكتملة</span><span class="kpi-ico c-green"><i data-icon="cart"></i></span></div>
        <div class="kpi-value">342</div>
        <div class="kpi-bottom"><span class="kpi-note"><span class="trend up">+9%</span> هذا الشهر</span><span data-spark="orders" data-color="#0e9f6e"></span></div>
      </div>
      <div class="card kpi">
        <div class="kpi-top"><span class="kpi-label">معدل إكمال الدورات</span><span class="kpi-ico c-amber"><i data-icon="award"></i></span></div>
        <div class="kpi-value">64%</div>
        <div class="kpi-bottom"><span class="kpi-note"><span class="trend down">-2%</span> هذا الشهر</span><span data-spark="completion" data-color="#c27803"></span></div>
      </div>
    </div>

    <!-- revenue + sources -->
    <div class="grid g-main">
      <div class="card">
        <div class="card-head">
          <div><h3>الإيرادات</h3><p>الدورات مقابل الخدمات، بالدولار</p></div>
          <div class="seg" id="revRange">
            <button class="on" data-range="12">12 شهراً</button>
            <button data-range="6">6 أشهر</button>
            <button data-range="3">3 أشهر</button>
          </div>
        </div>
        <div class="card-body">
          <div class="legend" style="margin-bottom:10px">
            <span><i style="background:#0066ff"></i>الدورات والورش</span>
            <span><i style="background:#0b0d12"></i>خدمات التطوير</span>
          </div>
          <div id="revenueChart"></div>
        </div>
        <div class="stat-row">
          <div class="mini-stat"><b id="revTotal">—</b><small>إجمالي الفترة</small></div>
          <div class="mini-stat"><b id="revCourses">—</b><small>من الدورات</small></div>
          <div class="mini-stat"><b id="revServices">—</b><small>من الخدمات</small></div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><div><h3>مصادر الزيارات</h3><p>آخر 30 يوماً</p></div><a class="link" href="{{ route('analytics') }}">التفاصيل</a></div>
        <div class="card-body">
          <div id="sourcesChart"></div>
          <div class="list" id="sourcesList" style="margin-top:16px"></div>
        </div>
      </div>
    </div>

    <!-- orders + activity -->
    <div class="grid g-main">
      <div class="card">
        <div class="card-head"><div><h3>أحدث الطلبات</h3><p>آخر عمليات الشراء على المنصة</p></div><a class="btn btn-ghost btn-sm" href="{{ route('orders.index') }}">كل الطلبات <i data-icon="arrow" class="sm"></i></a></div>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>الطلب</th><th>العميل</th><th>المنتج</th><th>المبلغ</th><th>الحالة</th></tr></thead>
            <tbody id="recentOrders"></tbody>
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
        <div class="card-head"><div><h3>الدورات الأعلى دخلاً</h3></div><a class="link" href="{{ route('courses.index') }}">الكل</a></div>
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
