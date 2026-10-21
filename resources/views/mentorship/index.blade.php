@extends('layouts.dashboard')

@section('title', 'الإرشاد')
@section('page', 'mentorship')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>الإرشاد</span></div>
        <h1>الإرشاد</h1>
        <p>راجع طلبات الطلاب وإشعارات التحويل، وحدّد مواعيد الجلسات.</p>
      </div>
      <div class="page-actions"><span class="badge info" id="seats" hidden></span></div>
    </div>

    <div class="card">
      <div class="tabs" data-tabs="mentorship" role="tablist">
        <button class="on" data-tab="requests" role="tab">الطلبات</button>
        <button data-tab="settings" role="tab">الإعدادات</button>
      </div>

      <div class="tab-panel on" data-panel-group="mentorship" data-panel="requests">
        <div class="toolbar"><div class="seg" id="statusSeg"></div></div>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>الطلب</th><th>الطالب</th><th>الباقة</th><th>المبلغ</th><th>الحالة</th><th>التاريخ</th></tr></thead>
            <tbody id="requestRows"></tbody>
          </table>
        </div>
      </div>

      <form class="tab-panel card-body" data-panel-group="mentorship" data-panel="settings" id="settingsForm" novalidate>
        <div class="setting-row"><div><b>استقبال الطلبات</b><p>عند الإيقاف تبقى صفحة الإرشاد في الموقع، مع رسالة أن الطلبات مغلقة حالياً. يلزم رقم محفظة واحد على الأقل.</p></div><label class="switch"><input type="checkbox" id="enabled"><span class="track"></span></label></div>

        <div class="form-grid" style="margin-top:18px">
          <div class="field"><label for="capacity">عدد المقاعد</label><input class="input ltr" id="capacity" type="number" min="1" max="100"><small class="muted">الطلبات المقبولة والجارية تشغل مقعداً حتى تكتمل.</small></div>
          <div class="field"><label for="paymentHours">مهلة الدفع بعد القبول (ساعة)</label><input class="input ltr" id="paymentHours" type="number" min="12" max="24"><small class="muted">من 12 إلى 24 ساعة؛ بعدها يُلغى الطلب تلقائياً ويعود المقعد متاحاً.</small></div>
        </div>

        <h3 style="margin:26px 0 6px">المحافظ</h3>
        <p class="muted" style="margin-bottom:12px">تظهر للطالب بعد قبول طلبه فقط، ولا تظهر في صفحة الموقع العامة.</p>
        <div class="form-grid" id="wallets"></div>

        <div style="display:flex;align-items:center;gap:12px;margin:26px 0 12px">
          <h3 style="margin:0">الباقات</h3>
          <span class="grow"></span>
          <button type="button" class="btn btn-sm btn-ghost" id="addPackage"><i data-icon="plus" class="sm"></i>باقة جديدة</button>
        </div>
        <div id="packages" style="display:flex;flex-direction:column;gap:14px"></div>

        <div class="field" style="margin-top:22px"><label for="policy">سياسة الإلغاء والاسترجاع</label><textarea class="textarea" id="policy" rows="3" maxlength="1000"></textarea><small class="muted">تظهر في صفحة الإرشاد، ويوافق عليها الطالب قبل إرسال طلبه.</small></div>

        <div style="display:flex;justify-content:flex-end;margin-top:20px"><button class="btn btn-primary" type="submit"><i data-icon="save" class="sm"></i>حفظ الإعدادات</button></div>
      </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/mentorship.js') }}"></script>
@endpush
