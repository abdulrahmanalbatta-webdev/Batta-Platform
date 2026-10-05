@extends('layouts.dashboard')

@section('title', 'الطلبات')
@section('page', 'orders')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>الطلبات</span></div>
        <h1>الطلبات والمبيعات</h1>
        <p>الدفع يدوي: بعد ما تستلم المبلغ من الطالب سجّل الطلب هنا فتُفتح له الدورة.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-ghost" id="export"><i data-icon="download" class="sm"></i>تصدير CSV</button>
        <button class="btn btn-primary" id="newOrder" data-requires="manage_sales"><i data-icon="plus" class="sm"></i>طلب يدوي</button>
      </div>
    </div>

    <div class="grid g4" id="orderStats"></div>

    <div class="card">
      <div class="toolbar">
        <label class="search"><i data-icon="search" class="sm"></i><input id="q" type="search" placeholder="رقم الطلب، اسم العميل، البريد…" aria-label="بحث"></label>
        <div class="seg" id="statusSeg"></div>
        <span class="grow"></span>
        <select class="select" id="typeFilter" style="width:auto;height:40px" aria-label="النوع"><option value="">كل الأنواع</option><option>دورة</option><option>ورشة</option><option>اشتراك</option></select>
        <select class="select" id="methodFilter" style="width:auto;height:40px" aria-label="طريقة الدفع"><option value="">كل طرق الدفع</option><option>تحويل بنكي</option><option>محفظة إلكترونية</option><option>نقداً</option></select>
      </div>
      <div id="table"></div>
    </div>
@endsection

@push('modals')
  <div class="modal" id="orderModal" aria-hidden="true">
    <form class="modal-box" id="orderForm" novalidate>
      <div class="modal-head"><h3>طلب يدوي</h3><button type="button" class="btn-icon" data-close aria-label="إغلاق"><i data-icon="close"></i></button></div>
      <div class="modal-body form-grid">
        <div class="field full"><label for="oStudent">الطالب *</label><select class="select" id="oStudent" required></select></div>
        <div class="field"><label for="oType">النوع</label><select class="select" id="oType"><option value="course">دورة</option><option value="workshop">ورشة</option><option value="pro-month">شهر Pro</option></select></div>
        <div class="field"><label for="oItem">العنصر *</label><select class="select" id="oItem"></select></div>
        <div class="field"><label for="oMethod">طريقة الدفع</label><select class="select" id="oMethod"><option value="bank-transfer">تحويل بنكي</option><option value="wallet">محفظة إلكترونية</option><option value="cash">نقداً</option></select></div>
        <div class="field"><label for="oCoupon">كوبون</label><input class="input mono ltr" id="oCoupon" placeholder="اختياري" style="text-transform:uppercase"></div>
        <div class="field full"><label class="switch"><input type="checkbox" id="oPaid" checked><span class="track"></span>تم استلام المبلغ (يُفتح الوصول فوراً)</label><span class="hint">ألغِ التحديد لتسجيله معلّقاً حتى يصل الدفع.</span></div>
        <div class="field full"><div class="card" style="padding:12px 14px;display:flex;justify-content:space-between"><span class="muted">السعر</span><b class="num" id="oPrice">—</b></div></div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>إلغاء</button><button class="btn btn-primary" type="submit" id="oSubmit">تسجيل الطلب</button></div>
    </form>
  </div>
@endpush

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/orders.js') }}"></script>
@endpush
