<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @yield('meta')
    @yield('title')
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/common.css') }}" />
    @yield('css')
</head>
<body>
    <header class="header">
        <div class="header__inner">
            <div class="header-left">
                <a href="{{ auth()->check() && auth()->user()->isAdmin() ? '/admin/attendance/list' : '/attendance' }}"><img src="{{ asset('images/logo.svg') }}" alt="coachtech" class="header-logo"></a>
            </div>
            @if(auth()->check() && request()->path() != 'login' && request()->path() != 'register' && request()->path() != 'admin/login' && request()->path() != 'email/verify')
            <div class="header-right">
                <form action="/logout" method="post">
                    @csrf
                <input id="drawer_input" class="drawer_hidden" type="checkbox">
                <label for="drawer_input" class="drawer_open"><span></span></label>
                <nav class="header-nav">
                    <ul class="header-nav-list">
                        @if (!auth()->user()->isAdmin())
                            <li class="header-nav-item"><a href="/attendance" class="attendance-link">勤怠</a></li>
                            <li class="header-nav-item"><a href="/attendance/list" class="attendance-list-link">勤怠一覧</a></li>
                            <li class="header-nav-item"><a href="/stamp_correction_request/list" class="application-link">申請</a></li>
                        @else
                            <li class="header-nav-item"><a href="/admin/attendance/list" class="attendance-list-link">勤怠一覧</a></li>
                            <li class="header-nav-item"><a href="/admin/staff/list" class="staff-list-link">スタッフ一覧</a></li>
                            <li class="header-nav-item"><a href="/stamp_correction_request/list" class="application-list-link">申請一覧</a></li>
                        @endif
                        <li class="header-nav-item"><button class="logout-link">ログアウト</button></li>
                    </ul>
                </nav>
                </form>
            </div>
            @endif
        </div>
    </header>
    <main>
        @yield('content')
    </main>
    @yield('script')
    @yield('style')
</body>
</html>