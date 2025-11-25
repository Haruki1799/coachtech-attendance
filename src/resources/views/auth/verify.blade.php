@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/verify.css') }}">
@endsection

@section('hide-nav')
@endsection

@section('content')
<div class="verify__content">
    <div class="verify__heading">
        <h2>登録していメールアドレスに認証メールを送付しました。<br>
                メール認証を完了してください。</h2>
    </div>

    @if (session('resent'))
    <div class="verify__alert">
        <p>認証メールを再送しました。</p>
    </div>
    @endif

    <div class="verify__actions">

        <a href="http://localhost:8025/#" target="_blank" class="verify__button-link">
            認証はこちらから
        </a>
        <form method="POST" action="{{ route('verification.resend') }}">
            @csrf
            <button type="submit" class="verify__button">認証メールを再送する</button>
        </form>
    </div>
</div>
@endsection