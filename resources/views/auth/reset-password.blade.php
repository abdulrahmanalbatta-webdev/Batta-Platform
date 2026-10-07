@extends('layouts.guest')

@php($invite = request()->boolean('invite'))

@section('title', $invite ? 'قبول الدعوة' : 'تعيين كلمة مرور جديدة')
@section('page', 'reset-password')

@section('content')
  <div class="auth">
    <section class="auth-form">
      <a href="{{ route('login') }}" style="display:inline-flex"><img src="{{ asset('assets/dashboard/img/logo.png') }}" alt="Batta" style="height:34px;width:auto"></a>
      <div class="inner">
        <div>
          <h1 style="font-size:28px">{{ $invite ? 'أهلاً بك في الفريق 👋' : 'كلمة مرور جديدة' }}</h1>
          <p class="muted">{{ $invite ? 'اختر كلمة مرور لحسابك لتبدأ استخدام لوحة التحكم.' : 'اختر كلمة مرور جديدة لحسابك.' }}</p>
        </div>
        <form id="resetForm" novalidate style="display:flex;flex-direction:column;gap:16px" data-token="{{ $token }}" data-invite="{{ $invite ? '1' : '0' }}">
          <div class="field">
            <label for="email">البريد الإلكتروني</label>
            <input class="input ltr" id="email" type="email" autocomplete="username" value="{{ request('email') }}" readonly>
          </div>
          <div class="field">
            <label for="password">كلمة المرور الجديدة</label>
            <input class="input ltr" id="password" type="password" autocomplete="new-password">
            <span class="hint">8 أحرف على الأقل، مع رقم وحرف كبير وحرف صغير.</span>
          </div>
          <div class="field">
            <label for="password_confirmation">تأكيد كلمة المرور</label>
            <input class="input ltr" id="password_confirmation" type="password" autocomplete="new-password">
          </div>
          <button class="btn btn-primary btn-lg" type="submit" id="submit">{{ $invite ? 'قبول الدعوة' : 'حفظ كلمة المرور' }}</button>
          <a class="link" href="{{ route('login') }}" style="font-size:13px;text-align:center">العودة لتسجيل الدخول</a>
        </form>
      </div>
      <small class="muted">© 2026 Batta. جميع الحقوق محفوظة.</small>
    </section>

    <aside class="auth-side">
      <div class="inner">
        <img src="{{ asset('assets/dashboard/img/logo-white.png') }}" alt="" style="height:40px;width:auto;align-self:flex-start">
        <h2>كل منصتك في لوحة واحدة: الدورات، الورش، الطلاب والتسجيلات.</h2>
      </div>
    </aside>
  </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dashboard/js/pages/reset-password.js') }}"></script>
@endpush
