@extends('errors.layout')

@section('code', '500')
@section('title', 'حدث خطأ غير متوقّع')
@section('message', 'واجهت اللوحة مشكلة أثناء تنفيذ طلبك وسُجّلت تلقائياً. حاول مرة أخرى بعد قليل.')

@section('actions')
  <button type="button" class="btn btn-primary" onclick="location.reload()">إعادة المحاولة</button>
  <a class="btn btn-ghost" href="{{ url('/') }}">العودة للرئيسية</a>
@endsection
