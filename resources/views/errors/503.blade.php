@extends('errors.layout')

@section('code', '503')
@section('title', 'اللوحة قيد الصيانة')
@section('message', 'نُجري تحديثاً سريعاً على اللوحة، وستعود خلال دقائق.')

@section('actions')
  <button type="button" class="btn btn-primary" onclick="location.reload()">تحديث الصفحة</button>
@endsection
