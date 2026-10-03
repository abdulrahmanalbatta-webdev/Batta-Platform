@extends('layouts.dashboard')

@section('title', 'الكوبونات')
@section('page', 'coupons')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>الكوبونات</span></div>
        <h1>كوبونات الخصم</h1>
        <p>أنشئ أكواد خصم للحملات والطلاب.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" data-open="couponModal"><i data-icon="plus" class="sm"></i>كوبون جديد</button>
      </div>
    </div>

    <div class="grid g3" id="couponStats"></div>

    <div class="card">
      <div class="card-head"><h3>كل الكوبونات</h3></div>
      <div id="table"></div>
    </div>
@endsection

@push('modals')
  <div class="modal" id="couponModal" aria-hidden="true">
    <form class="modal-box" id="couponForm" novalidate>
      <div class="modal-head"><h3>كوبون جديد</h3><button type="button" class="btn-icon" data-close aria-label="إغلاق"><i data-icon="close"></i></button></div>
      <div class="modal-body form-grid">
        <div class="field full">
          <label for="code">الكود *</label>
          <div style="display:flex;gap:8px"><input class="input mono" id="code" placeholder="WELCOME20" style="text-transform:uppercase;text-align:left" required><button type="button" class="btn btn-ghost" id="genCode"><i data-icon="sparkle" class="sm"></i>توليد</button></div>
        </div>
        <div class="field"><label for="ctype">نوع الخصم</label><select class="select" id="ctype"><option value="percent">نسبة مئوية %</option><option value="fixed">مبلغ ثابت $</option></select></div>
        <div class="field"><label for="cvalue">القيمة *</label><input class="input ltr" id="cvalue" type="number" min="1" value="20" required></div>
        <div class="field"><label for="climit">حد الاستخدام</label><input class="input ltr" id="climit" type="number" min="0" value="100"><span class="hint">0 = بلا حد</span></div>
        <div class="field"><label for="cexp">ينتهي في *</label><input class="input" id="cexp" type="date" required></div>
        <div class="field full"><label for="cscope">ينطبق على</label><select class="select" id="cscope"><option>كل الدورات</option><option>الورش</option><option>Next.js من الصفر إلى الإنتاج</option><option>أساسيات الويب الحديث</option><option>برنامج المطوّر المستقل</option></select></div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>إلغاء</button><button class="btn btn-primary" type="submit">إنشاء الكوبون</button></div>
    </form>
  </div>
@endpush

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/coupons.js') }}"></script>
@endpush
