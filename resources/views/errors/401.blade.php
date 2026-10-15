@extends('errors.layout')

@section('code', '401')
@section('title', 'يلزم تسجيل الدخول')
@section('message', 'سجّل الدخول أولاً لتتمكّن من فتح هذه الصفحة.')

@section('actions')
  <a class="btn btn-primary" href="{{ route('login') }}">تسجيل الدخول</a>
@endsection
