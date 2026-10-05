{{-- Route URLs, the API base, the asset base, the sidebar counts and the signed-in member for the dashboard scripts (js/app.js reads window.APP) --}}
@use('App\Http\Resources\UserResource')
@use('App\Support\NavCounts')
@php
    $appConfig = [
        'app_name' => config('app.name'),
        'currency_symbol' => $currencySymbol,
        // the public site (settings → عام), for links copied from the dashboard
        'site_url' => $siteUrl,
        'assets' => asset('assets/dashboard'),
        'api' => url('dashboard/api/v1'),
        'user' => auth()->check() ? (new UserResource(auth()->user()))->resolve(request()) : null,
        // unread conversations, reviews waiting for moderation and new project requests
        'counts' => auth()->check() ? NavCounts::all() : null,
        'routes' => [
            'dashboard' => route('dashboard'),
            'analytics' => route('analytics'),
            'courses' => route('courses.index'),
            'course-create' => route('courses.create'),
            'course-edit' => route('courses.edit', '__ID__'),
            'workshops' => route('workshops.index'),
            'articles' => route('articles.index'),
            'article-create' => route('articles.create'),
            'article-edit' => route('articles.edit', '__ID__'),
            'tools' => route('tools.index'),
            'orders' => route('orders.index'),
            'coupons' => route('coupons.index'),
            'leads' => route('leads.index'),
            'students' => route('students.index'),
            'subscribers' => route('subscribers.index'),
            'messages' => route('messages.index'),
            'reviews' => route('reviews.index'),
            'settings' => route('settings.index'),
            'profile' => route('profile'),
            'login' => route('login'),
        ],
    ];
@endphp
<meta name="csrf-token" content="{{ csrf_token() }}">
<script nonce="{{ $cspNonce ?? '' }}">
    window.APP = @json($appConfig);
</script>
