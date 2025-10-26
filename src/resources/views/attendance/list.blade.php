@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/list.css') }}">
@endsection

@section('content')
<div class="container">
    <h2>勤怠一覧</h2>

    <div class="month-nav">
        <div class="month-nav__side">
            <form method="GET" action="{{ route('attendance.list') }}">
                <input type="hidden" name="year" value="{{ $prevMonth->year }}">
                <input type="hidden" name="month" value="{{ $prevMonth->month }}">
                <button class="month-nav__button">← 前月</button>
            </form>
        </div>

        <div class="month-nav__center">
            <span class="month-nav__icon">🗓️</span>
            <span class="month-nav__label">{{ $currentMonth->format('Y/m') }}</span>
        </div>

        <div class="month-nav__side">
            <form method="GET" action="{{ route('attendance.list') }}">
                <input type="hidden" name="year" value="{{ $nextMonth->year }}">
                <input type="hidden" name="month" value="{{ $nextMonth->month }}">
                <button class="month-nav__button">翌月 →</button>
            </form>
        </div>
    </div>


    <table class="table">
        <thead>
            <tr>
                <th>日付</th>
                <th>出勤</th>
                <th>退勤</th>
                <th>休憩</th>
                <th>合計</th>
                <th>備考</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($daysInMonth as $day)
            @php
            $key = $day->format('Y-m-d');
            $attendance = $attendances[$key] ?? null;
            $breakMinutes = $attendance?->breakTimes->sum(function ($break) {
            return \Carbon\Carbon::parse($break->ended_at)->diffInMinutes(\Carbon\Carbon::parse($break->started_at));
            }) ?? 0;
            $breakFormatted = sprintf('%d:%02d', intdiv($breakMinutes, 60), $breakMinutes % 60);
            @endphp
            <tr>
                <td>{{ $day->format('m/d') }}({{ $day->isoFormat('ddd') }})</td>
                <td>{{ optional($attendance?->started_at)->format('H:i') ?? '' }}</td>
                <td>{{ optional($attendance?->ended_at)->format('H:i') ?? '' }}</td>
                <td>{{ $attendance ? $breakFormatted : '' }}</td>
                <td>{{ $attendance?->total_hours ?? '' }}</td>
                <td>
                    <a href="{{ $attendance
                        ? route('attendance.detail', ['id' => $attendance->id])
                        : route('attendance.detail', ['date' => $day->format('Y-m-d')]) }}">
                        詳細
                    </a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection