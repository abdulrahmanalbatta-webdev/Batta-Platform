@extends('layouts.dashboard')

@section('title', 'الإعدادات')
@section('page', 'settings')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>الإعدادات</span></div>
        <h1>الإعدادات</h1>
        <p>إعدادات المنصة، الدفع، الإشعارات والفريق.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" id="saveAll" form="settingsForm"><i data-icon="save" class="sm"></i>حفظ التغييرات</button>
      </div>
    </div>

    <div class="card">
      <div class="tabs" data-tabs="settings" role="tablist">
        <button class="on" data-tab="general" role="tab">عام</button>
        <button data-tab="payments" role="tab">الدفع</button>
        <button data-tab="notifications" role="tab">الإشعارات</button>
        <button data-tab="team" role="tab">الفريق</button>
        <button data-tab="security" role="tab">الأمان</button>
      </div>

      <form id="settingsForm" novalidate>
        <!-- general -->
        <div class="tab-panel on card-body" data-panel-group="settings" data-panel="general">
          <div class="form-grid">
            <div class="field"><label for="siteName">اسم المنصة</label><input class="input" id="siteName" value="Batta"></div>
            <div class="field"><label for="siteUrl">الرابط</label><input class="input ltr" id="siteUrl" value="https://batta.dev"></div>
            <div class="field full"><label for="tagline">الوصف المختصر</label><input class="input" id="tagline" value="أبني مواقع وأنظمة ويب، وأعلّم كيف تُبنى."></div>
            <div class="field"><label for="email">بريد التواصل</label><input class="input ltr" id="email" type="email" value="hello@batta.dev"></div>
            <div class="field"><label for="wa">رقم واتساب</label><input class="input ltr" id="wa" value="+970 59 000 0000"></div>
            <div class="field"><label for="lang">اللغة الافتراضية</label><select class="select" id="lang"><option>العربية</option><option>English</option></select></div>
            <div class="field"><label for="tz">المنطقة الزمنية</label><select class="select" id="tz"><option>Asia/Jerusalem (GMT+3)</option><option>Asia/Riyadh (GMT+3)</option><option>Asia/Amman (GMT+3)</option><option>Africa/Cairo (GMT+3)</option></select></div>
          </div>
          <div style="margin-top:22px">
            <div class="setting-row"><div><b>وضع الصيانة</b><p>إخفاء الموقع مؤقتاً عن الزوار وإظهار صفحة "نعود قريباً".</p></div><label class="switch"><input type="checkbox" id="maintenance"><span class="track"></span></label></div>
            <div class="setting-row"><div><b>السماح بالتسجيل</b><p>يمكن للزوار إنشاء حساب جديد في المنصة.</p></div><label class="switch"><input type="checkbox" checked><span class="track"></span></label></div>
            <div class="setting-row"><div><b>التعليقات على المقالات</b><p>تفعيل التعليقات مع مراجعتها قبل النشر.</p></div><label class="switch"><input type="checkbox" checked><span class="track"></span></label></div>
          </div>
        </div>

        <!-- payments -->
        <div class="tab-panel card-body" data-panel-group="settings" data-panel="payments">
          <div class="grid g3" style="margin-bottom:22px">
            <div class="card" style="padding:18px;display:flex;flex-direction:column;gap:12px">
              <div style="display:flex;justify-content:space-between;align-items:center"><b style="color:var(--fg)">Stripe</b><span class="badge dot success">متصل</span></div>
              <small class="muted">بطاقات Visa / Mastercard و Apple Pay.</small>
              <button type="button" class="btn btn-sm btn-ghost" data-gw="Stripe">إدارة</button>
            </div>
            <div class="card" style="padding:18px;display:flex;flex-direction:column;gap:12px">
              <div style="display:flex;justify-content:space-between;align-items:center"><b style="color:var(--fg)">PayPal</b><span class="badge dot success">متصل</span></div>
              <small class="muted">الدفع عبر حساب PayPal.</small>
              <button type="button" class="btn btn-sm btn-ghost" data-gw="PayPal">إدارة</button>
            </div>
            <div class="card" style="padding:18px;display:flex;flex-direction:column;gap:12px">
              <div style="display:flex;justify-content:space-between;align-items:center"><b style="color:var(--fg)">تحويل بنكي</b><span class="badge dot">غير مفعّل</span></div>
              <small class="muted">تأكيد يدوي للطلبات.</small>
              <button type="button" class="btn btn-sm btn-soft" data-gw="التحويل البنكي">تفعيل</button>
            </div>
          </div>
          <div class="form-grid">
            <div class="field"><label for="currency">العملة</label><select class="select" id="currency"><option>دولار أمريكي (USD)</option><option>ريال سعودي (SAR)</option><option>دينار أردني (JOD)</option></select></div>
            <div class="field"><label for="vat">ضريبة القيمة المضافة %</label><input class="input ltr" id="vat" type="number" min="0" max="30" value="0"></div>
            <div class="field full"><label for="invoiceNote">ملاحظة الفاتورة</label><textarea class="textarea" id="invoiceNote" rows="3">شكراً لثقتك بـ Batta. للاستفسار: hello@batta.dev</textarea></div>
          </div>
          <div style="margin-top:18px">
            <div class="setting-row"><div><b>ضمان استرداد 14 يوماً</b><p>إظهار سياسة الاسترداد في صفحات الدورات.</p></div><label class="switch"><input type="checkbox" checked><span class="track"></span></label></div>
          </div>
        </div>

        <!-- notifications -->
        <div class="tab-panel card-body" data-panel-group="settings" data-panel="notifications">
          <div class="setting-row"><div><b>طلب شراء جديد</b><p>بريد فوري عند كل عملية شراء.</p></div><label class="switch"><input type="checkbox" checked><span class="track"></span></label></div>
          <div class="setting-row"><div><b>طلب مشروع جديد</b><p>إشعار عند وصول طلب من صفحة الخدمات.</p></div><label class="switch"><input type="checkbox" checked><span class="track"></span></label></div>
          <div class="setting-row"><div><b>تقييم بانتظار المراجعة</b><p>تنبيه عند وصول تقييم جديد.</p></div><label class="switch"><input type="checkbox" checked><span class="track"></span></label></div>
          <div class="setting-row"><div><b>رسائل الطلاب</b><p>إشعار عند وصول رسالة جديدة.</p></div><label class="switch"><input type="checkbox"><span class="track"></span></label></div>
          <div class="setting-row"><div><b>التقرير الأسبوعي</b><p>ملخص الإيرادات والزيارات كل يوم أحد.</p></div><label class="switch"><input type="checkbox" checked><span class="track"></span></label></div>
          <div class="setting-row"><div><b>النشرة البريدية للطلاب</b><p>إرسال المقالات الجديدة للمشتركين تلقائياً.</p></div><label class="switch"><input type="checkbox" checked><span class="track"></span></label></div>
        </div>

        <!-- team -->
        <div class="tab-panel" data-panel-group="settings" data-panel="team">
          <div class="toolbar">
            <b style="color:var(--fg)">أعضاء الفريق</b><span class="grow"></span>
            <button type="button" class="btn btn-sm btn-primary" id="inviteBtn" data-open="inviteModal" hidden><i data-icon="plus" class="sm"></i>دعوة عضو</button>
          </div>
          <div class="list" id="team"></div>
        </div>

        <!-- security -->
        <div class="tab-panel card-body" data-panel-group="settings" data-panel="security">
          <div class="setting-row"><div><b>التحقق بخطوتين</b><p>رمز من تطبيق المصادقة عند كل تسجيل دخول.</p></div><label class="switch"><input type="checkbox" checked><span class="track"></span></label></div>
          <div class="setting-row"><div><b>تنبيه الدخول من جهاز جديد</b><p>إرسال بريد عند تسجيل دخول غير معتاد.</p></div><label class="switch"><input type="checkbox" checked><span class="track"></span></label></div>
          <div class="setting-row"><div><b>انتهاء الجلسة تلقائياً</b><p>تسجيل الخروج بعد فترة من عدم النشاط.</p></div><select class="select" style="width:auto;height:40px" aria-label="مدة الجلسة"><option>30 دقيقة</option><option selected>ساعتان</option><option>يوم</option></select></div>
          <div style="margin-top:22px">
            <div class="label" style="margin-bottom:10px">الأجهزة المتصلة</div>
            <div class="card" id="sessions"></div>
          </div>
          <div class="card" style="margin-top:22px;padding:18px;border-color:#fecaca;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
            <div style="flex:1;min-width:220px"><b style="color:var(--danger)">تصدير ومسح البيانات</b><p class="muted" style="font-size:13px">تنزيل نسخة من كل بيانات المنصة أو حذفها نهائياً.</p></div>
            <button type="button" class="btn btn-ghost" id="exportData"><i data-icon="download" class="sm"></i>تصدير</button>
            <button type="button" class="btn btn-danger-soft" id="wipe"><i data-icon="trash" class="sm"></i>حذف كل البيانات</button>
          </div>
        </div>
      </form>
    </div>
@endsection

@push('modals')
  <div class="modal" id="inviteModal" aria-hidden="true">
    <form class="modal-box" id="inviteForm" novalidate>
      <div class="modal-head"><h3>دعوة عضو للفريق</h3><button type="button" class="btn-icon" data-close aria-label="إغلاق"><i data-icon="close"></i></button></div>
      <div class="modal-body form-grid">
        <div class="field"><label for="iName">الاسم *</label><input class="input" id="iName"></div>
        <div class="field"><label for="iEmail">البريد *</label><input class="input ltr" id="iEmail" type="email"></div>
        <div class="field full"><label for="iRole">الصلاحية</label><select class="select" id="iRole"></select></div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>إلغاء</button><button class="btn btn-primary" type="submit"><i data-icon="send" class="sm"></i>إرسال الدعوة</button></div>
    </form>
  </div>
@endpush

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/settings.js') }}"></script>
@endpush
