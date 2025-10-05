@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 600px; margin: auto; padding: 2rem;">
    <h2>メール認証のお願い</h2>

    @if (session('resent'))
    <div class="alert alert-success" role="alert">
        認証メールを再送しました。
    </div>
    @endif

    <p>登録したメールアドレスに認証リンクを送信しました。<br>
        メールを確認し、リンクをクリックして認証を完了してください。</p>

    <p>メールが届いていない場合は、以下のボタンから再送できます。</p>

    <form method="POST" action="{{ route('verification.resend') }}">
        @csrf
        <button type="submit" class="btn btn-primary">認証メールを再送する</button>
    </form>

    <hr>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-link">ログアウトして戻る</button>
    </form>
</div>
@endsection