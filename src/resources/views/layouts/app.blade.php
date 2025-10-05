<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Couchtech free market</title>
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
    <link rel="stylesheet" href="{{ asset('css/common.css') }}">
    @yield('css')
</head>

<body>
    <header class="header">
        <div class="header__inner">
            <div class="header-utilities">
                <a class="header__logo" href="/">
                    <img src="{{ asset('img/logo.svg') }}" alt="coachtech">
                </a>

                @if (!View::hasSection('hide-nav'))
                <div class="header-search">
                    <form id="search-form" action="{{ route('search') }}" method="GET">
                        <input type="text" name="keyword" placeholder="なにをお探しですか？" class="search-input" id="search-input"
                            value="{{ request('keyword') }}">
                        <input type="hidden" name="page" value="{{ request('page') }}">
                    </form>
                </div>

                <nav>
                    <ul class="header-nav">
                        @auth
                        <li class="header-nav__item">
                            <form class="form" action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button class="header-nav__button">ログアウト</button>
                            </form>
                        </li>
                        <li class="header-nav__item">
                            <a class="header-nav__link--mypage" href="{{ route('mypage') }}">マイページ</a>
                        </li>
                        <li class="header-nav__item">
                            <a class="header-nav__link--listing" href="{{ route('sell') }}">出品</a>
                        </li>
                        @endauth

                        @guest
                        <li class="header-nav__item">
                            <a class="header-nav__link--login" href="{{ route('login') }}">ログイン</a>
                        </li>
                        <li class="header-nav__item">
                            <a class="header-nav__link--mypage" href="{{ route('mypage') }}">マイページ</a>
                        </li>
                        <li class="header-nav__item">
                            <a class="header-nav__link--listing" href="{{ route('sell') }}">出品</a>
                        </li>
                        @endguest
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