@extends('errors.layout')

@section('code', '419')
@section('title', 'انتهت صلاحية الصفحة')
@section('message', 'بقيت الصفحة مفتوحة وقتاً طويلاً فانتهت الجلسة. حدّث الصفحة ثم أعد المحاولة.')

@section('actions')
  <button type="button" class="btn btn-primary" onclick="location.reload()">تحديث الصفحة</button>
  <a class="btn btn-ghost" href="{{ url('/') }}">العودة للرئيسية</a>
@endsection
