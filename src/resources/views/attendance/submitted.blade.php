@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/submitted.css') }}">
@endsection

@section('content')
<div class="container">
    <h2>勤怠詳細</h2>

    <form method="GET" action="{{ route('attendance.submitted', ['id' => $attendance->id]) }}"></form>

    <table class="table table-bordered detail-table">
        <tbody>
            <tr>
                <th>名前</th>
                <td>{{ $attendance->user->name }}</td>
            </tr>
            <tr>
                <th>日付</th>
                <td>
                    <div class="date-inline">
                        <div>{{ $attendance->work_date->format('Y年') }}</div>
                        <div>{{ $attendance->work_date->format('n月j日') }}</div>
                    </div>
                </td>
            </tr>
            <tr>
                <th>出勤・退勤</th>
                <td>
                    {{ optional($attendance->started_at)->format('H:i') }} 〜
                    {{ optional($attendance->ended_at)->format('H:i') }}
                </td>
            </tr>
            @foreach ($attendance->breakTimes as $index => $break)
            <tr>
                <th>休憩{{ $index + 1 }}</th>
                <td>
                    {{ optional($break->started_at)->format('H:i') }} 〜
                    {{ optional($break->ended_at)->format('H:i') }}
                </td>
            </tr>
            @endforeach
            <tr>
                <th>備考</th>
                <td>{{ $attendance->note }}</td>
            </tr>
        </tbody>
    </table>


    <div class="alert-warning">
        ※承認待ちのため修正はできません。
    </div>
</div>
@endsection