@extends('layouts.guest')

@section('title', 'الصفحة غير موجودة')
@section('page', '404')

@section('content')
  <div class="notfound">
    <div style="display:flex;flex-direction:column;align-items:center;gap:16px;max-width:440px">
      <img src="{{ asset('assets/dashboard/img/logo.png') }}" alt="Batta" style="height:34px;width:auto;margin-bottom:12px">
      <h1>404</h1>
      <h2 style="font-size:22px">الصفحة غير موجودة</h2>
      <p class="muted">ربما تم نقل هذه الصفحة أو حذفها، أو أن الرابط غير صحيح.</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center">
        <a class="btn btn-primary" href="{{ route('dashboard') }}"><i data-icon="home" class="sm"></i>العودة للرئيسية</a>
        <button class="btn btn-ghost" data-back><i data-icon="chevron-right" class="sm"></i>الصفحة السابقة</button>
      </div>
    </div>
  </div>
@endsection
