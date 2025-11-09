<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Couchtech attendance</title>
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
    <link rel="stylesheet" href="{{ asset('css/common.css') }}">
    @yield('css')
</head>

<body>
    <header class="header">
        <div class="header__inner">
            <div class="header-utilities">
                @if (Auth::guard('admin')->check())
                <a class="header__logo" href="{{ route('admin.list') }}">
                    <img src="{{ asset('img/logo.svg') }}" alt="coachtech">
                </a>
                @else
                <a class="header__logo" href="{{ route('attendance') }}">
                    <img src="{{ asset('img/logo.svg') }}" alt="coachtech">
                </a>
                @endif

                @if (!View::hasSection('hide-nav'))
                <nav>
                    <ul class="header-nav">
                        @auth('admin')
                        <li class="header-nav__item">
                            <form class="form" action="{{ route('admin.list') }}" method="GET">
                                <button class="header-nav__button">勤怠一覧</button>
                            </form>
                        </li>
                        <li class="header-nav__item">
                            <form class="form" action="{{ route('admin.staff.list') }}" method="GET">
                                <button class="header-nav__button">スタッフ一覧</button>
                            </form>
                        </li>
                        <li class="header-nav__item">
                            <form class="form" action="{{ route('admin.request.index') }}" method="GET">
                                <button class="header-nav__button">申請一覧</button>
                            </form>
                        </li>
                        <li class="header-nav__item">
                            <form class="form" action="{{ route('admin.logout') }}" method="POST">
                                @csrf
                                <button class="header-nav__button">ログアウト</button>
                            </form>
                        </li>
                        @elseif (Auth::guard('web')->check())
                        <li class="header-nav__item">
                            <form class="form" action="{{ route('attendance') }}" method="GET">
                                <button class="header-nav__button">勤怠</button>
                            </form>
                        </li>
                        <li class="header-nav__item">
                            <form class="form" action="{{ route('attendance.list') }}" method="GET">
                                <button class="header-nav__button">勤怠一覧</button>
                            </form>
                        </li>
                        <li class="header-nav__item">
                            <form class="form" action="{{ route('request.index') }}" method="GET">
                                <button class="header-nav__button">申請</button>
                            </form>
                        </li>
                        <li class="header-nav__item">
                            <form class="form" action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button class="header-nav__button">ログアウト</button>
                            </form>
                        </li>
                        @endauth
                    </ul>
                </nav>
                @endif
            </div>
        </div>
    </header>

    <main>
        @yield('content')
        @yield('js')
    </main>
</body>

</html>