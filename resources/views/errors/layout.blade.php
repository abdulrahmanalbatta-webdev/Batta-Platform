{{-- error pages: standalone (no settings, no database, no app.js) so they still render when the app itself is failing --}}
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') | لوحة التحكم</title>
    <link rel="icon" href="{{ asset('assets/dashboard/img/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/dashboard/css/style.css') }}">
</head>
<body data-page="error">
  <main class="error-page">
    <div class="error-card">
      <a href="{{ url('/') }}" class="error-logo"><img src="{{ asset('assets/dashboard/img/lockup.png') }}" alt="{{ config('app.name') }}"></a>
      <div class="error-code" aria-hidden="true">@yield('code')</div>
      <h1>@yield('title')</h1>
      <p class="muted">@yield('message')</p>
      <div class="error-actions">
        @section('actions')
          <a class="btn btn-primary" href="{{ url('/') }}">العودة للرئيسية</a>
          <button type="button" class="btn btn-ghost" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')">الصفحة السابقة</button>
        @show
      </div>
    </div>
  </main>
</body>
</html>
