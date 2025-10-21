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
                <a class="header__logo" href="/attendance">
                    <img src="{{ asset('img/logo.svg') }}" alt="coachtech">
                </a>

                @if (!View::hasSection('hide-nav'))


                <nav>
                    <ul class="header-nav">
                        @auth
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