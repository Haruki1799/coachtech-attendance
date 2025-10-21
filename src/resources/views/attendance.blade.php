@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance.css') }}">
@endsection

@section('content')
<div class="container">
    {{-- ステータス表示 --}}
    <button class="status">
        @if ($isClockedOut)
        退勤済
        @elseif ($isOnBreak)
        休憩中
        @elseif ($isWorking)
        出勤中
        @else
        勤務外
        @endif
    </button>

    {{-- 日付と時間は常に表示 --}}
    <div class="date">{{ \Carbon\Carbon::now()->isoFormat('YYYY年M月D日(ddd)') }}</div>
    <div class="time" id="time"></div>

    {{-- メッセージとボタン表示 --}}
    @if ($isClockedOut)
    <div class="message">お疲れ様でした。</div>
    @elseif (!$isWorking)
    <form method="POST" action="{{ route('attendance.clockin') }}">
        @csrf
        <button type="submit" class="btn-attendance-start">出勤</button>
    </form>
    @elseif ($isOnBreak)
    <form method="POST" action="{{ route('attendance.breakout') }}">
        @csrf
        <button type="submit" class="btn-break-end">休憩戻</button>
    </form>
    @else
    <div class="button-group">
        <form method="POST" action="{{ route('attendance.clockout') }}">
            @csrf
            <button type="submit" class="btn-attendance-end">退勤</button>
        </form>
        <form method="POST" action="{{ route('attendance.breakin') }}">
            @csrf
            <button type="submit" class="btn-break-start">休憩入</button>
        </form>
    </div>
    @endif
</div>
@endsection

@section('js')
<script>
    function updateTime() {
        const now = new Date();
        const timeEl = document.getElementById('time');
        timeEl.textContent = now.toLocaleTimeString('ja-JP', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    updateTime();
    setInterval(updateTime, 60000);
</script>
@endsection