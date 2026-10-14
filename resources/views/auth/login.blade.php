@extends('layouts.guest')

@section('title', 'تسجيل الدخول')
@section('page', 'login')

@section('content')
  <div class="auth">
    <section class="auth-form">
      <a href="{{ route('dashboard') }}" style="display:inline-flex"><img src="{{ asset('assets/dashboard/img/lockup.png') }}" alt="{{ config('app.name') }}" style="height:44px;width:auto"></a>
      <div class="inner">
        <div>
          <h1 style="font-size:28px">مرحباً بعودتك</h1>
          <p class="muted">سجّل الدخول لإدارة منصة Batta.</p>
        </div>
        <form id="loginForm" novalidate style="display:flex;flex-direction:column;gap:16px">
          <div class="field">
            <label for="email">البريد الإلكتروني</label>
            <input class="input ltr" id="email" type="email" autocomplete="username">
            <span class="error" id="emailErr" hidden></span>
          </div>
          <div class="field">
            <div style="display:flex;justify-content:space-between"><label for="password">كلمة المرور</label><a class="link" href="#" id="forgot" style="font-size:13px">نسيت كلمة المرور؟</a></div>
            <div class="input-group">
              <input class="input ltr" id="password" type="password" autocomplete="current-password">
              <button type="button" class="btn-icon" id="togglePw" aria-label="إظهار كلمة المرور" style="border:0"><i data-icon="eye" class="sm"></i></button>
            </div>
            <span class="error" id="pwErr" hidden></span>
          </div>
          <label class="check"><input type="checkbox" id="remember" checked>تذكرني على هذا الجهاز</label>
          <button class="btn btn-primary btn-lg" type="submit" id="submit">تسجيل الدخول</button>
        </form>
      </div>
      <small class="muted">© 2026 Batta. جميع الحقوق محفوظة.</small>
    </section>

    <aside class="auth-side">
      <div class="inner">
        <img src="{{ asset('assets/dashboard/img/lockup-white.png') }}" alt="" style="height:52px;width:auto;align-self:flex-start">
        <h2>كل منصتك في لوحة واحدة: الدورات، الورش، الطلاب والتسجيلات.</h2>
        <p style="opacity:.75">تابع التسجيلات لحظياً، ردّ على طلابك، وأدر طلبات المشاريع من مكان واحد.</p>
      </div>
    </aside>
  </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/login.js') }}"></script>
@endpush
