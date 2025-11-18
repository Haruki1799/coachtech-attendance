@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/adminlist.css') }}">
@endsection

@section('content')
<div class="container">
    <h2>{{ $targetDate->format('Y年m月d日') }}の勤怠</h2>

    <div class="day-nav">
        <div class="day-nav__side">
            <form method="GET" action="{{ route('admin.list') }}">
                <input type="hidden" name="date" value="{{ $targetDate->copy()->subDay()->toDateString() }}">
                <button class="day-nav__button">← 前日</button>
            </form>
        </div>

        <div class="day-nav__center">
            <span class="month-nav__icon">🗓️</span>
            <span class="month-nav__label">{{ $targetDate->format('Y/m/d') }}</span>
        </div>

        <div class="day-nav__side">
            <form method="GET" action="{{ route('admin.list') }}">
                <input type="hidden" name="date" value="{{ $targetDate->copy()->addDay()->toDateString() }}">
                <button class="day-nav__button">翌日 →</button>
            </form>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>名前</th>
                <th>出勤</th>
                <th>退勤</th>
                <th>休憩</th>
                <th>合計</th>
                <th>備考</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
            @php
            $attendance = $attendances[$user->id] ?? null;
            $breakMinutes = $attendance?->breakTimes->sum(function ($break) {
            return \Carbon\Carbon::parse($break->ended_at)->diffInMinutes(\Carbon\Carbon::parse($break->started_at));
            }) ?? 0;
            $breakFormatted = sprintf('%d:%02d', intdiv($breakMinutes, 60), $breakMinutes % 60);
            @endphp
            <tr>
                <td>{{ $user->name }}</td>
                <td>{{ optional($attendance?->started_at)->format('H:i') ?? '' }}</td>
                <td>{{ optional($attendance?->ended_at)->format('H:i') ?? '' }}</td>
                <td>{{ $attendance ? $breakFormatted : '' }}</td>
                <td>{{ $attendance?->total_hours ?? '' }}</td>
                <td>
                    <a href="{{ $attendance
                        ? route('admin.attendance.detail', ['id' => $attendance->id])
                        : route('admin.attendance.detail', ['date' => $targetDate->format('Y-m-d'), 'user_id' => $user->id]) }}">
                        詳細
                    </a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection