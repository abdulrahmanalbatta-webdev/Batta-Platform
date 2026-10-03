@extends('layouts.dashboard')

@section('title', 'الملف الشخصي')
@section('page', 'profile')

@section('content')
    <div class="card profile-card">
      <div class="profile-cover"></div>
      <div class="profile-head">
        <label class="avatar xl" id="avatarWrap" style="background:var(--ink);cursor:pointer" title="تغيير الصورة">
          <span>ع</span><img id="avatarImg" src="{{ asset('assets/dashboard/img/profile-face.jpg') }}" alt="الصورة الشخصية">
          <input type="file" id="avatarInput" accept="image/*" hidden>
        </label>
        <div class="grow">
          <h2 id="pName">عبدالرحمن البطة</h2>
          <p class="muted">مدير المنصة · مطوّر ويب ومدرّب</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <a class="btn btn-ghost" href="#" id="viewSite"><i data-icon="external" class="sm"></i>عرض صفحتي</a>
          <label class="btn btn-dark" for="avatarInput" style="cursor:pointer"><i data-icon="image" class="sm"></i>تغيير الصورة</label>
        </div>
      </div>
      <div class="stat-row" style="grid-template-columns:repeat(4,minmax(0,1fr))" id="pStats"></div>
    </div>

    <div class="grid g-main">
      <div style="display:flex;flex-direction:column;gap:20px;min-width:0">
        <form class="card" id="infoForm" novalidate>
          <div class="card-head"><h3>المعلومات الشخصية</h3></div>
          <div class="card-body form-grid">
            <div class="field"><label for="fName">الاسم الكامل *</label><input class="input" id="fName" value="عبدالرحمن البطة"></div>
            <div class="field"><label for="fTitle">المسمى</label><input class="input" id="fTitle" value="مطوّر ويب ومدرّب"></div>
            <div class="field"><label for="fEmail">البريد *</label><input class="input ltr" id="fEmail" type="email" value="admin@batta.dev"></div>
            <div class="field"><label for="fPhone">الهاتف</label><input class="input ltr" id="fPhone" value="+970 59 000 0000"></div>
            <div class="field full"><label for="fBio">نبذة</label><textarea class="textarea" id="fBio" rows="4" maxlength="280">أبني مواقع وأنظمة ويب للشركات وروّاد الأعمال، وأعلّم البرمجة بالعربية من خلال دورات وورش عملية.</textarea><span class="hint"><span id="bioCount">0</span> / 280</span></div>
            <div class="field"><label for="fGithub">GitHub</label><div class="input-group"><span class="addon">github.com/</span><input class="input ltr" id="fGithub" value="batta"></div></div>
            <div class="field"><label for="fLinkedin">LinkedIn</label><div class="input-group"><span class="addon">linkedin.com/in/</span><input class="input ltr" id="fLinkedin" value="batta"></div></div>
          </div>
          <div class="card-foot" style="display:flex;justify-content:flex-end;gap:10px"><button type="reset" class="btn btn-ghost">تراجع</button><button class="btn btn-primary" type="submit">حفظ المعلومات</button></div>
        </form>

        <form class="card" id="pwForm" novalidate>
          <div class="card-head"><h3>تغيير كلمة المرور</h3></div>
          <div class="card-body form-grid">
            <div class="field full"><label for="pwOld">كلمة المرور الحالية</label><input class="input ltr" id="pwOld" type="password" autocomplete="current-password"></div>
            <div class="field"><label for="pwNew">كلمة المرور الجديدة</label><input class="input ltr" id="pwNew" type="password" autocomplete="new-password"><div class="pw-meter" id="pwMeter"><i></i><i></i><i></i><i></i></div><span class="hint" id="pwHint">8 أحرف على الأقل، مع رقم وحرف كبير.</span></div>
            <div class="field"><label for="pwConfirm">تأكيد كلمة المرور</label><input class="input ltr" id="pwConfirm" type="password" autocomplete="new-password"></div>
          </div>
          <div class="card-foot" style="display:flex;justify-content:flex-end"><button class="btn btn-dark" type="submit"><i data-icon="lock" class="sm"></i>تحديث كلمة المرور</button></div>
        </form>
      </div>

      <div class="card">
        <div class="card-head"><h3>نشاطي الأخير</h3></div>
        <ul class="timeline" id="myActivity" style="padding-top:18px"></ul>
      </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/profile.js') }}"></script>
@endpush
