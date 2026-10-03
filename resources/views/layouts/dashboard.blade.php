<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | لوحة التحكم</title>
    <link rel="icon" href="{{ asset('assets/dashboard/img/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/dashboard/css/style.css') }}">
    @include('layouts.partials.app-config')
    {{-- before first paint: app shell + saved sidebar state --}}
    <script src="{{ asset('assets/dashboard/js/boot.js') }}"></script>
</head>
{{-- the sidebar and topbar are built by js/app.js around #content --}}
<body data-page="@yield('page')" @isset($id) data-id="{{ $id }}" @endisset>
    <main id="content">
@yield('content')
    </main>

@stack('modals')

    <script src="{{ asset('assets/dashboard/js/data.js') }}"></script>
    <script src="{{ asset('assets/dashboard/js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
