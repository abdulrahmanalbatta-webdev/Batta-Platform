{{-- Route URLs, the API base and the asset base for the dashboard scripts (js/app.js reads window.APP) --}}
@php
    $appConfig = [
        'assets' => asset('assets/dashboard'),
        'api' => url('dashboard/api/v1'),
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
            'messages' => route('messages.index'),
            'reviews' => route('reviews.index'),
            'settings' => route('settings.index'),
            'profile' => route('profile'),
            'login' => route('login'),
        ],
    ];
@endphp
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    window.APP = @json($appConfig);
</script>
