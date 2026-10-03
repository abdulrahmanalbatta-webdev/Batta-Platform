<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | لوحة التحكم</title>
    <link rel="icon" href="{{ asset('assets/dashboard/img/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/dashboard/css/style.css') }}">
    @include('layouts.partials.app-config')
</head>
{{-- standalone pages without the sidebar (login, errors) --}}
<body data-page="@yield('page')">
@yield('content')

    <script src="{{ asset('assets/dashboard/js/data.js') }}"></script>
    <script src="{{ asset('assets/dashboard/js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
