@extends('layouts.guest')

@section('title', 'إلغاء الاشتراك')
@section('page', 'unsubscribe')

@section('content')
  {{-- reached from the signed link in a newsletter email --}}
  <div class="notfound">
    <div style="display:flex;flex-direction:column;align-items:center;gap:16px;max-width:440px;text-align:center">
      <img src="{{ asset('assets/dashboard/img/logo.png') }}" alt="{{ config('app.name') }}" style="height:34px;width:auto;margin-bottom:12px">
      @if ($done)
        <h2 style="font-size:22px">تم إلغاء اشتراكك</h2>
        <p class="muted">لن تصلك رسائل المقالات الجديدة على <bdi class="mono">{{ $email }}</bdi> بعد الآن.</p>
      @else
        <h2 style="font-size:22px">إلغاء الاشتراك في النشرة</h2>
        <p class="muted">هل تريد إيقاف رسائل المقالات الجديدة على <bdi class="mono">{{ $email }}</bdi>؟</p>
        <form method="post" action="{{ request()->fullUrl() }}">
          @csrf
          <button class="btn btn-primary" type="submit">نعم، ألغِ اشتراكي</button>
        </form>
      @endif
    </div>
  </div>
@endsection
