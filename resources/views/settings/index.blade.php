@extends('layouts.dashboard')

@section('title', 'الإعدادات')
@section('page', 'settings')

@section('content')
    <div class="page-head">
      <div>
        <div class="crumbs"><a href="{{ route('dashboard') }}">الرئيسية</a><span class="sep">/</span><span>الإعدادات</span></div>
        <h1>الإعدادات</h1>
        <p>إعدادات المنصة، البريد، الإشعارات والفريق.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" id="saveAll" form="settingsForm" data-requires="manage_settings"><i data-icon="save" class="sm"></i>حفظ التغييرات</button>
      </div>
    </div>

    <div class="card">
      <div class="tabs" data-tabs="settings" role="tablist">
        <button class="on" data-tab="general" role="tab">عام</button>
        <button data-tab="mail" role="tab">البريد</button>
        <button data-tab="analytics" role="tab">الإحصاءات</button>
        <button data-tab="notifications" role="tab">الإشعارات</button>
        <button data-tab="team" role="tab">الفريق</button>
        <button data-tab="security" role="tab">الأمان</button>
        <button data-tab="activity" role="tab">سجل النشاط</button>
      </div>

      <form id="settingsForm" novalidate>
        <!-- general: shown on the public site (GET site-settings) -->
        <div class="tab-panel on card-body" data-panel-group="settings" data-panel="general">
          <div class="form-grid">
            <div class="field"><label for="siteName">اسم المنصة</label><input class="input" id="siteName" data-setting="site_name" maxlength="60"></div>
            <div class="field"><label for="siteUrl">رابط الموقع</label><input class="input ltr" id="siteUrl" data-setting="site_url" type="url"></div>
            <div class="field full"><label for="tagline">الوصف المختصر</label><input class="input" id="tagline" data-setting="tagline" maxlength="160"></div>
            <div class="field"><label for="email">بريد التواصل</label><input class="input ltr" id="email" data-setting="contact_email" type="email"></div>
            <div class="field"><label for="wa">رقم واتساب</label><input class="input ltr" id="wa" data-setting="whatsapp" placeholder="+970 59 000 0000"></div>
            <div class="field"><label for="currency">عملة ميزانيات المشاريع</label><select class="select" id="currency" data-setting="currency"><option value="USD">دولار أمريكي (USD)</option><option value="SAR">ريال سعودي (SAR)</option><option value="JOD">دينار أردني (JOD)</option></select></div>
          </div>
          <div style="margin-top:22px">
            <div class="setting-row"><div><b>وضع الصيانة</b><p>إخفاء الموقع مؤقتاً عن الزوار وإظهار صفحة "نعود قريباً".</p></div><label class="switch"><input type="checkbox" id="maintenance" data-setting="maintenance_mode"><span class="track"></span></label></div>
            <div class="setting-row"><div><b>السماح بالتسجيل</b><p>يمكن للزوار إنشاء حساب جديد في المنصة.</p></div><label class="switch"><input type="checkbox" data-setting="registration_open"><span class="track"></span></label></div>
            <div class="setting-row"><div><b>التعليقات</b><p>تعليقات الطلاب وردودهم على المقالات والدورات والورش، مع مراجعتها قبل النشر.</p></div><label class="switch"><input type="checkbox" data-setting="article_comments"><span class="track"></span></label></div>
          </div>
          <p class="muted" style="font-size:12.5px;margin-top:14px">هذه الإعدادات يقرأها الموقع العام من <span class="mono ltr">/api/v1/settings</span>.</p>
        </div>

        <!-- mail -->
        <div class="tab-panel card-body" data-panel-group="settings" data-panel="mail">
          <p class="muted" style="margin-bottom:14px">للمالك فقط. خادم SMTP لكل رسائل المنصة (الدعوات، الفواتير، الردود، التنبيهات). اترك الخادم فارغاً لاستخدام إعدادات <span class="mono ltr">MAIL_*</span> في ملف <span class="mono ltr">.env</span>.</p>
          <div class="form-grid">
            <div class="field"><label for="mailHost">خادم SMTP</label><input class="input ltr" id="mailHost" data-setting="mail_host" placeholder="smtp.example.com"></div>
            <div class="field"><label for="mailPort">المنفذ</label><input class="input ltr" id="mailPort" data-setting="mail_port" type="number" min="1" max="65535"></div>
            <div class="field"><label for="mailEnc">التشفير</label><select class="select" id="mailEnc" data-setting="mail_encryption"><option value="tls">TLS (المنفذ 587)</option><option value="ssl">SSL (المنفذ 465)</option></select></div>
            <div class="field"><label for="mailUser">اسم المستخدم</label><input class="input ltr" id="mailUser" data-setting="mail_username" autocomplete="off"></div>
            <div class="field"><label for="mailPass">كلمة المرور</label><input class="input ltr" id="mailPass" type="password" data-secret="mail_password" autocomplete="new-password"></div>
            <div class="field"><label for="mailFrom">بريد المرسل</label><input class="input ltr" id="mailFrom" data-setting="mail_from_address" type="email" placeholder="no-reply@batta.dev"></div>
            <div class="field"><label for="mailFromName">اسم المرسل</label><input class="input" id="mailFromName" data-setting="mail_from_name" placeholder="اسم المنصة"></div>
          </div>
          <div style="margin-top:18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap" data-requires="manage_platform_data">
            <button type="button" class="btn btn-ghost" id="testEmail"><i data-icon="mail" class="sm"></i>إرسال رسالة تجريبية</button>
            <small class="muted">تُرسل إلى بريدك بعد حفظ التغييرات.</small>
          </div>
        </div>

        <!-- analytics: Google Analytics 4 for the public site's traffic -->
        <div class="tab-panel card-body" data-panel-group="settings" data-panel="analytics">
          <p class="muted" style="margin-bottom:14px">زيارات الموقع العام من Google Analytics 4: الموقع يرسلها بمعرّف القياس، واللوحة تقرأ تقاريرها في صفحة التحليلات عبر حساب خدمة (Service account) له صلاحية القراءة.</p>
          <div class="form-grid">
            <div class="field"><label for="gaMeasurement">معرّف القياس (Measurement ID)</label><input class="input ltr" id="gaMeasurement" data-setting="ga_measurement_id" placeholder="G-XXXXXXXXXX" autocomplete="off"><small class="muted">يقرؤه الموقع العام من <span class="mono ltr">/api/v1/settings</span> ليُحمّل Google Analytics.</small></div>
            <div class="field"><label for="gaProperty">رقم الموقع (Property ID)</label><input class="input ltr" id="gaProperty" data-setting="ga_property_id" placeholder="123456789" inputmode="numeric" autocomplete="off"><small class="muted">من Google Analytics: الإدارة ← تفاصيل الموقع. رقم فقط.</small></div>
            <div class="field full"><label for="gaCredentials">مفتاح حساب الخدمة (JSON) — للمالك فقط</label><textarea class="textarea ltr mono" id="gaCredentials" rows="5" data-secret="ga_credentials" autocomplete="off" spellcheck="false"></textarea></div>
          </div>
          <div style="margin-top:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap" data-requires="manage_settings">
            <button type="button" class="btn btn-ghost" id="testAnalytics"><i data-icon="refresh" class="sm"></i>اختبار الاتصال</button>
            <small class="muted">بعد حفظ التغييرات.</small>
          </div>
          <details style="margin-top:20px">
            <summary style="cursor:pointer;font-weight:700;color:var(--fg)">كيف أحصل على المفتاح؟</summary>
            <ol class="muted" style="margin:10px 0 0;padding-inline-start:20px;line-height:1.9">
              <li>في <span class="ltr">Google Cloud Console</span> أنشئ مشروعاً (أو اختر مشروعاً) وفعّل <span class="ltr">Google Analytics Data API</span>.</li>
              <li>من <span class="ltr">IAM &amp; Admin ← Service accounts</span> أنشئ حساب خدمة، ثم من تبويب <span class="ltr">Keys</span> أضف مفتاحاً بصيغة JSON وانسخ محتوى الملف هنا.</li>
              <li>في Google Analytics: الإدارة ← إدارة الوصول إلى الموقع، أضف بريد حساب الخدمة (ينتهي بـ <span class="ltr">iam.gserviceaccount.com</span>) بدور <b>Viewer</b>.</li>
            </ol>
          </details>
        </div>

        <!-- notifications -->
        <div class="tab-panel card-body" data-panel-group="settings" data-panel="notifications">
          <p class="muted" style="margin-bottom:6px">إشعاراتك تظهر في الجرس دائماً؛ اختر ما يصلك منها بالبريد أيضاً. يُحفظ كل تغيير فوراً.</p>
          <div id="notifPrefs"></div>
          <div class="label" style="margin:22px 0 4px">للمنصة كلها</div>
          <div class="setting-row"><div><b>التقرير الأسبوعي</b><p>ملخص تسجيلات الأسبوع للمالك والمدراء صباح كل أحد.</p></div><label class="switch"><input type="checkbox" data-setting="weekly_report"><span class="track"></span></label></div>
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
          <div class="setting-row"><div><b>التحقق بخطوتين <span class="badge" style="height:20px;font-size:11px">قريباً</span></b><p>رمز من تطبيق المصادقة عند كل تسجيل دخول.</p></div><label class="switch"><input type="checkbox" disabled><span class="track"></span></label></div>
          <div class="setting-row"><div><b>تنبيه الدخول من جهاز جديد</b><p>بريد للعضو عند تسجيل دخوله من متصفح أو شبكة لم يستخدمها من قبل.</p></div><label class="switch"><input type="checkbox" data-setting="new_device_alert"><span class="track"></span></label></div>
          <div class="setting-row"><div><b>انتهاء الجلسة تلقائياً</b><p>تسجيل الخروج بعد فترة من عدم النشاط.</p></div><select class="select" style="width:auto;height:40px" aria-label="مدة الجلسة" data-setting="session_lifetime"><option value="30">30 دقيقة</option><option value="120">ساعتان</option><option value="1440">يوم</option></select></div>
          <div style="margin-top:22px">
            <div class="label" style="margin-bottom:10px">الأجهزة المتصلة</div>
            <div class="card" id="sessions"></div>
          </div>
          <div class="card" style="margin-top:22px;padding:18px;border-color:#fecaca" data-requires="manage_platform_data">
            <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
              <div style="flex:1;min-width:220px"><b style="color:var(--danger)">تصدير ومسح البيانات</b><p class="muted" style="font-size:13px">نسخة ZIP من كل بيانات المنصة (تُجهَّز في الخلفية وتبقى 7 أيام)، أو حذفها نهائياً. للمالك فقط.</p></div>
              <button type="button" class="btn btn-ghost" id="exportData"><i data-icon="download" class="sm"></i>تجهيز نسخة</button>
              <button type="button" class="btn btn-danger-soft" id="wipe" data-open="wipeModal"><i data-icon="trash" class="sm"></i>حذف كل البيانات</button>
            </div>
            <div class="list" id="exports" style="margin-top:12px"></div>
          </div>
        </div>

        <!-- activity log -->
        <div class="tab-panel" data-panel-group="settings" data-panel="activity">
          <ul class="timeline" id="activityLog" style="padding:18px 0 4px"></ul>
          <div style="padding:0 18px 18px;text-align:center"><button type="button" class="btn btn-sm btn-ghost" id="moreActivity" hidden>عرض المزيد</button></div>
        </div>
      </form>
    </div>
@endsection

@push('modals')
  <div class="modal" id="wipeModal" aria-hidden="true">
    <form class="modal-box" id="wipeForm" novalidate>
      <div class="modal-head"><h3 style="color:var(--danger)">حذف كل بيانات المنصة</h3><button type="button" class="btn-icon" data-close aria-label="إغلاق"><i data-icon="close"></i></button></div>
      <div class="modal-body form-grid">
        <p class="full muted">سيتم حذف الدورات والورش والمقالات والأدوات والطلاب والطلبات والكوبونات والتقييمات والرسائل وطلبات المشاريع نهائياً مع ملفاتها. يبقى الفريق والإعدادات وسجل النشاط. ننصح بتجهيز نسخة أولاً.</p>
        <div class="field full"><label for="wipePassword">كلمة مرورك</label><input class="input ltr" id="wipePassword" type="password" autocomplete="current-password"></div>
        <div class="field full"><label for="wipeConfirm">اكتب: <b>احذف كل البيانات</b></label><input class="input" id="wipeConfirm" autocomplete="off"></div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>إلغاء</button><button class="btn btn-danger" type="submit" id="wipeSubmit" disabled>حذف نهائي</button></div>
    </form>
  </div>
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
