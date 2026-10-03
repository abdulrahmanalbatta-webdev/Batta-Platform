@extends('layouts.dashboard')

@section('title', 'الطلبات')
@section('page', 'orders')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>الطلبات</span></div>
        <h1>الطلبات والمبيعات</h1>
        <p>كل عمليات الشراء للدورات والورش والاشتراكات.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-ghost" id="export"><i data-icon="download" class="sm"></i>تصدير CSV</button>
      </div>
    </div>

    <div class="grid g4" id="orderStats"></div>

    <div class="card">
      <div class="toolbar">
        <label class="search"><i data-icon="search" class="sm"></i><input id="q" type="search" placeholder="رقم الطلب، اسم العميل، البريد…" aria-label="بحث"></label>
        <div class="seg" id="statusSeg"></div>
        <span class="grow"></span>
        <select class="select" id="typeFilter" style="width:auto;height:40px" aria-label="النوع"><option value="">كل الأنواع</option><option>دورة</option><option>ورشة</option><option>اشتراك</option></select>
        <select class="select" id="methodFilter" style="width:auto;height:40px" aria-label="طريقة الدفع"><option value="">كل طرق الدفع</option><option>بطاقة</option><option>PayPal</option><option>Apple Pay</option></select>
      </div>
      <div id="table"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/orders.js') }}"></script>
@endpush
